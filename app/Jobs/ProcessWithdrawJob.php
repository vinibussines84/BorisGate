<?php

namespace App\Jobs;

use App\Models\Withdraw;
use App\Services\Provider\XflowWithdraw;
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

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(Withdraw $withdraw, array $payload)
    {
        $this->withdrawId = $withdraw->id;
        $this->payload    = $payload;

        $this->onQueue('withdraws');
    }

    public function handle(
        XflowWithdraw $provider,
        WithdrawService $withdrawService
    ): void {
        $withdraw = Withdraw::find($this->withdrawId);

        if (!$withdraw) {
            Log::error('[ProcessWithdrawJob][XFLOW] ❌ Withdraw não encontrado');
            return;
        }

        Log::info('[ProcessWithdrawJob][XFLOW] 🚀 Iniciando saque', [
            'withdraw_id' => $withdraw->id,
        ]);

        // 🔒 Idempotência
        if ($withdraw->provider_reference) {
            Log::warning('[ProcessWithdrawJob][XFLOW] ⏭ Já enviado ao provider');
            return;
        }

        if (in_array($withdraw->status, [
            Withdraw::STATUS_PAID,
            Withdraw::STATUS_FAILED,
            Withdraw::STATUS_CANCELED,
        ], true)) {
            Log::warning('[ProcessWithdrawJob][XFLOW] ⏭ Saque já finalizado');
            return;
        }

        /**
         * ✅ PAYLOAD CORRETO DO DOMÍNIO
         * 🔥 NUNCA pix_key AQUI
         */
        $domainPayload = [
            'amount'      => (float) $this->payload['amount'],
            'external_id' => $this->payload['external_id'],
            'key'         => $this->payload['key'],       // ✅ OBRIGATÓRIO
            'key_type'    => $this->payload['key_type'],  // ✅ OBRIGATÓRIO
            'description' => $this->payload['description'] ?? 'Saque solicitado',
        ];

        try {
            $resp = $provider->withdraw(
                $domainPayload['amount'],
                $domainPayload
            );
        } catch (Throwable $e) {

            Log::error('[ProcessWithdrawJob][XFLOW] ❌ Erro ao chamar provider', [
                'error' => $e->getMessage(),
                'payload' => $domainPayload,
            ]);

            $withdrawService->refundLocal(
                $withdraw,
                'Erro ao criar saque na XFlow: ' . $e->getMessage()
            );
            return;
        }

        $providerId     = data_get($resp, 'id');
        $providerStatus = strtolower(data_get($resp, 'status', 'pending'));

        Log::info('[ProcessWithdrawJob][XFLOW] 🔁 Retorno provider', [
            'withdraw_id'     => $withdraw->id,
            'provider_id'     => $providerId,
            'provider_status' => $providerStatus,
        ]);

        if ($providerId) {
            $withdrawService->updateProviderReference(
                $withdraw,
                $providerId,
                $providerStatus,
                $resp
            );
        }
    }
}
