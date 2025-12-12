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

        /**
         * Evita duplicidade
         */
        if (!empty($withdraw->provider_reference)) {
            Log::warning('[ProcessWithdrawJob][XFLOW] ⏭ Já enviado ao provider');
            return;
        }

        /**
         * Evita reprocessar finalizados
         */
        if (in_array($withdraw->status, [
            Withdraw::STATUS_PAID,
            Withdraw::STATUS_FAILED,
            Withdraw::STATUS_CANCELED,
        ], true)) {
            Log::warning('[ProcessWithdrawJob][XFLOW] ⏭ Saque já finalizado');
            return;
        }

        /**
         * ✅ PAYLOAD PADRÃO (CORRETO)
         */
        $domainPayload = [
            'amount'       => (float) $this->payload['amount'],
            'external_id'  => $this->payload['external_id'],
            'pix_key'      => $this->payload['pix_key'],
            'key_type'     => strtoupper($this->payload['key_type']),
            'description'  => $this->payload['description'] ?? 'Saque solicitado',
        ];

        try {
            $resp = $provider->withdraw(
                $domainPayload['amount'],
                $domainPayload
            );

        } catch (Throwable $e) {

            if (str_contains($e->getMessage(), 'RATE_LIMIT')) {
                Log::warning('[ProcessWithdrawJob][XFLOW] ⏳ Rate limit — retry em 10s');
                $this->release(10);
                return;
            }

            Log::error('[ProcessWithdrawJob][XFLOW] ❌ Erro ao chamar provider', [
                'error' => $e->getMessage(),
            ]);

            $withdrawService->refundLocal(
                $withdraw,
                'Erro ao criar saque na XFlow: ' . $e->getMessage()
            );
            return;
        }

        /**
         * Resposta esperada:
         * { id, status }
         */
        $providerId     = data_get($resp, 'id');
        $providerStatus = strtolower(data_get($resp, 'status', 'pending'));

        Log::info('[ProcessWithdrawJob][XFLOW] 🔁 Retorno provider', [
            'withdraw_id'     => $withdraw->id,
            'provider_id'     => $providerId,
            'provider_status' => $providerStatus,
        ]);

        /**
         * Normalização de status
         */
        if (in_array($providerStatus, ['completed', 'success', 'paid'], true)) {
            $providerStatus = Withdraw::STATUS_PAID;
        } elseif (!in_array($providerStatus, ['pending', 'processing'], true)) {
            $providerStatus = Withdraw::STATUS_FAILED;
        }

        /**
         * Salva provider reference
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
         * Pago imediatamente
         */
        if ($providerStatus === Withdraw::STATUS_PAID) {
            $withdrawService->markAsPaid(
                $withdraw,
                payload: $resp,
                extra: [
                    'paid_at' => now(),
                    'provider_status' => 'paid',
                ]
            );

            Log::info('[ProcessWithdrawJob][XFLOW] ✅ Saque finalizado como PAID');
            return;
        }

        /**
         * Qualquer outro caso → estorno
         */
        $withdrawService->refundLocal(
            $withdraw,
            "XFlow retornou status inválido: {$providerStatus}"
        );

        Log::warning('[ProcessWithdrawJob][XFLOW] ❌ Saque FAILED', [
            'withdraw_id' => $withdraw->id,
            'status'      => $providerStatus,
        ]);
    }
}
