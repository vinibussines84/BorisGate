<?php

namespace App\Jobs;

use App\Models\Withdraw;
use App\Services\Provider\ProviderColdFyOut;
use App\Services\Withdraw\WithdrawService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWithdrawJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $withdrawId;
    public array $payload;

    public $tries   = 5;
    public $timeout = 60;

    public function __construct(Withdraw $withdraw, array $payload)
    {
        $this->withdrawId = $withdraw->id;
        $this->payload    = $payload;
        $this->onQueue('withdraws');
    }

    public function handle(
        ProviderColdFyOut $provider,
        WithdrawService $withdrawService
    ) {
        /*
        |--------------------------------------------------------------------------
        | 1) Buscar saque no banco
        |--------------------------------------------------------------------------
        */
        $withdraw = Withdraw::find($this->withdrawId);

        if (!$withdraw) {
            Log::error('[ProcessWithdrawJob] ❌ Withdraw não encontrado', [
                'withdraw_id' => $this->withdrawId,
            ]);
            return;
        }

        Log::info('[ProcessWithdrawJob] 🚀 Iniciando processamento ColdFy', [
            'withdraw_id' => $withdraw->id,
            'payload'     => $this->payload,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2) Verificar se já finalizado
        |--------------------------------------------------------------------------
        */
        if (in_array($withdraw->status, ['paid', 'failed', 'canceled'], true)) {
            Log::warning('[ProcessWithdrawJob] ⛔ Já finalizado — ignorando', [
                'withdraw_id' => $withdraw->id
            ]);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Montar payload final para o provider
        |--------------------------------------------------------------------------
        */
        $providerPayload = [
            'external_id'  => $this->payload['externalId'] ?? $this->payload['external_id'],
            'pix_key'      => $this->payload['pixKey'],
            'pix_key_type' => strtolower($this->payload['pixKeyType']),
            'description'  => $this->payload['description'],
            'amount'       => (float) $this->payload['amount'],
        ];

        Log::info('[ProcessWithdrawJob] 🔧 Payload ColdFy preparado', [
            'withdraw_id'      => $withdraw->id,
            'provider_payload' => $providerPayload,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 4) Tentar criar o saque no ColdFy
        |--------------------------------------------------------------------------
        */
        try {
            $resp = $provider->createCashout($providerPayload);

        } catch (Throwable $e) {

            Log::error('[ProcessWithdrawJob] ❌ Erro ao chamar ColdFy', [
                'withdraw_id' => $withdraw->id,
                'exception'   => $e->getMessage(),
            ]);

            // marca falha + estorna
            $withdrawService->refundLocal($withdraw, "Erro ao criar saque: " . $e->getMessage());

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 5) Extrair retorno do ColdFy PIX OUT
        |--------------------------------------------------------------------------
        |
        | Exemplo de retorno:
        |
        | {
        |   "event": "withdrawal.created",
        |   "withdrawal": {
        |       "id": "...",
        |       "status": "approved",
        |       ...
        |   }
        | }
        |
        |--------------------------------------------------------------------------
        */
        $withdrawal = data_get($resp, 'withdrawal');

        $providerId     = data_get($withdrawal, 'id');
        $providerStatus = strtolower(data_get($withdrawal, 'status', 'pending'));

        Log::info('[ProcessWithdrawJob] 🔁 Retorno ColdFy', [
            'withdraw_id'     => $withdraw->id,
            'provider_id'     => $providerId,
            'provider_status' => $providerStatus,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 6) Atualizar referência do provider
        |--------------------------------------------------------------------------
        */
        if ($providerId) {
            $withdrawService->updateProviderReference(
                $withdraw,
                $providerId,
                $providerStatus,
                $resp
            );
            $withdraw->refresh();
        }

        /*
        |--------------------------------------------------------------------------
        | 7) Se status = approved → marcar pago (no SEU padrão)
        |--------------------------------------------------------------------------
        */
        if ($providerStatus === 'approved') {

            $withdrawService->markAsPaid(
                $withdraw,
                payload: $resp,
                extra: [
                    'paid_at' => now(),
                    'provider_status' => $providerStatus
                ]
            );

            Log::info('[ProcessWithdrawJob] ✅ Saque aprovado (ColdFy)', [
                'withdraw_id' => $withdraw->id,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 8) Qualquer outro status → falha + estorno + webhook failed
        |--------------------------------------------------------------------------
        */
        $withdrawService->refundLocal(
            $withdraw,
            "ColdFy retornou status: {$providerStatus}"
        );

        Log::warning('[ProcessWithdrawJob] ❌ Saque recusado ColdFy', [
            'withdraw_id'     => $withdraw->id,
            'provider_status' => $providerStatus,
        ]);

        return;
    }
}
