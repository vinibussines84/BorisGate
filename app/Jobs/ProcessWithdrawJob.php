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
use App\Support\StatusMap;
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
        | 1) Buscar o saque
        |--------------------------------------------------------------------------
        */
        $withdraw = Withdraw::find($this->withdrawId);

        if (!$withdraw) {
            Log::error('[ProcessWithdrawJob] ❌ Withdraw não encontrado', [
                'withdraw_id' => $this->withdrawId,
            ]);
            return;
        }

        Log::info('[ProcessWithdrawJob] 🚀 Iniciando processamento (ColdFy)', [
            'withdraw_id' => $withdraw->id,
            'payload'     => $this->payload,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2) Evitar reprocessar saque já finalizado
        |--------------------------------------------------------------------------
        */
        if (in_array($withdraw->status, ['paid', 'failed', 'canceled'], true)) {
            Log::warning('[ProcessWithdrawJob] ⚠️ Ignorado — saque já finalizado', [
                'withdraw_id' => $withdraw->id,
                'status'      => $withdraw->status,
            ]);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Montar payload FINAL padronizado
        |--------------------------------------------------------------------------
        */
        $providerPayload = [
            'external_id'  => $this->payload['externalId'] ?? $this->payload['external_id'],
            'pix_key'      => $this->payload['pixKey'],
            'pix_key_type' => strtolower($this->payload['pixKeyType']),
            'description'  => $this->payload['description'],
            'amount'       => (float) $this->payload['amount'],
        ];

        Log::info('[ProcessWithdrawJob] 🔧 Payload preparado para ColdFy', [
            'withdraw_id'       => $withdraw->id,
            'provider_payload'   => $providerPayload,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 4) Enviar saque para o provider (ColdFy)
        |--------------------------------------------------------------------------
        */
        try {
            $resp = $provider->createCashout($providerPayload);
        } catch (Throwable $e) {

            Log::error('[ProcessWithdrawJob] 💥 Erro ao chamar ColdFy', [
                'withdraw_id' => $withdraw->id,
                'exception'   => $e->getMessage(),
            ]);

            // Marcar falha
            $withdrawService->fail($withdraw, 'provider_error');

            // Estornar saldo
            $withdrawService->refund($withdraw);

            // Disparar webhook OUT (erro)
            $withdrawService->notifyWebhook($withdraw, 'failed');

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 5) Extrair dados retornados do ColdFy
        |--------------------------------------------------------------------------
        |
        | ColdFy PIX OUT responde assim:
        |
        | {
        |   "event": "withdrawal.created",
        |   "withdrawal": {
        |       "id": "...",
        |       "status": "approved",
        |       ...
        |   }
        | }
        |--------------------------------------------------------------------------
        */
        $withdrawal = data_get($resp, 'withdrawal');

        $providerId = data_get($withdrawal, 'id');
        $providerStatus = strtolower(data_get($withdrawal, 'status', 'pending'));

        Log::info('[ProcessWithdrawJob] 🔁 Retorno ColdFy recebido', [
            'withdraw_id'       => $withdraw->id,
            'provider_id'       => $providerId,
            'provider_status'   => $providerStatus,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 6) Salvar referência do provedor
        |--------------------------------------------------------------------------
        */
        if ($providerId) {
            $withdrawService->updateProviderReference(
                $withdraw,
                $providerId,
                $providerStatus,
                $resp
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 7) Avaliar status retornado (ColdFy não usa webhook OUT)
        |--------------------------------------------------------------------------
        |
        | Se status = "approved" → Saque pago
        | Caso contrário → Saque falhou
        |--------------------------------------------------------------------------
        */

        if ($providerStatus === 'approved') {

            $withdrawService->markPaid($withdraw);

            Log::info('[ProcessWithdrawJob] ✅ Saque aprovado (ColdFy)', [
                'withdraw_id' => $withdraw->id,
                'provider_id' => $providerId,
            ]);

            // Webhook OUT — sucesso
            $withdrawService->notifyWebhook($withdraw, 'paid');

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 8) Qualquer outro status → falha
        |--------------------------------------------------------------------------
        */
        $withdrawService->fail($withdraw, $providerStatus);

        // Estorna saldo
        $withdrawService->refund($withdraw);

        Log::warning('[ProcessWithdrawJob] ❌ Saque não aprovado', [
            'withdraw_id' => $withdraw->id,
            'provider_status' => $providerStatus,
        ]);

        // Webhook OUT — falha
        $withdrawService->notifyWebhook($withdraw, 'failed');
    }
}
