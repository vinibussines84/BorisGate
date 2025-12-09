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
        $withdraw = Withdraw::find($this->withdrawId);

        if (!$withdraw) {
            Log::error('[ProcessWithdrawJob] ❌ Withdraw não encontrado');
            return;
        }

        Log::info('[ProcessWithdrawJob] 🚀 Iniciando processamento', [
            'withdraw_id' => $withdraw->id
        ]);

        /**
         * ✔ IMPEDIR REENVIO AO PROVIDER
         */
        if (!empty($withdraw->provider_reference)) {
            Log::warning('[ProcessWithdrawJob] ⛔ Já enviado anteriormente ao provider — ignorando retry');
            return;
        }

        if (in_array($withdraw->status, ['paid', 'failed', 'canceled'], true)) {
            Log::warning('[ProcessWithdrawJob] ⛔ Saque já finalizado — ignorando');
            return;
        }

        $providerPayload = [
            'external_id'  => $this->payload['externalId'] ?? $this->payload['external_id'],
            'pix_key'      => $this->payload['pixKey'],
            'pix_key_type' => strtolower($this->payload['pixKeyType']),
            'description'  => $this->payload['description'],
            'amount'       => (float) $this->payload['amount'],
        ];

        try {
            $resp = $provider->createCashout($providerPayload);

        } catch (Throwable $e) {

            // ⚠️ RATE_LIMIT = NÃO É FALHA
            if ($e->getMessage() === "RATE_LIMIT") {
                Log::warning('[ProcessWithdrawJob] ⏳ Rate limit ColdFy — retry automático');
                $this->release(10);
                return;
            }

            // ❌ Erro real → estornar
            Log::error('[ProcessWithdrawJob] ❌ Erro real ao chamar provider', [
                'error' => $e->getMessage(),
            ]);

            $withdrawService->refundLocal($withdraw, "Erro ao criar saque: " . $e->getMessage());
            return;
        }

        $withdrawal = data_get($resp, 'withdrawal');
        $providerId     = data_get($withdrawal, 'id');
        $providerStatus = strtolower(data_get($withdrawal, 'status', 'pending'));

        Log::info('[ProcessWithdrawJob] 🔁 Retorno ColdFy', [
            'withdraw_id' => $withdraw->id,
            'provider_id' => $providerId,
            'status'      => $providerStatus,
        ]);

        /**
         * ✔ Atualizar referência do provider (IMPORTANTE!)
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

        /**
         * ✔ Caso aprovado → marcar como pago
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

            Log::info('[ProcessWithdrawJob] ✅ Saque aprovado');
            return;
        }

        /**
         * ❌ Qualquer outro status real de falha
         */
        $withdrawService->refundLocal($withdraw, "ColdFy status: {$providerStatus}");

        Log::warning('[ProcessWithdrawJob] ❌ Saque recusado pelo provider');
    }
}
