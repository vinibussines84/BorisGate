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

        if (! $withdraw) {
            Log::error('[ProcessWithdrawJob][XFLOW] ❌ Withdraw não encontrado', [
                'withdraw_id' => $this->withdrawId,
            ]);
            return;
        }

        Log::info('[ProcessWithdrawJob][XFLOW] 🚀 Iniciando saque', [
            'withdraw_id' => $withdraw->id,
        ]);

        /**
         * 🔒 IDOTEMPÊNCIA
         */
        if ($withdraw->provider_reference) {
            Log::warning('[ProcessWithdrawJob][XFLOW] ⏭ Já enviado ao provider', [
                'provider_reference' => $withdraw->provider_reference,
            ]);
            return;
        }

        if (in_array($withdraw->status, [
            Withdraw::STATUS_PAID,
            Withdraw::STATUS_FAILED,
            Withdraw::STATUS_CANCELED,
        ], true)) {
            Log::warning('[ProcessWithdrawJob][XFLOW] ⏭ Saque já finalizado', [
                'status' => $withdraw->status,
            ]);
            return;
        }

        /**
         * ✅ PAYLOAD DE DOMÍNIO (XFLOW)
         */
        $domainPayload = [
            'amount'      => (float) $this->payload['amount'],
            'external_id' => $this->payload['external_id'],
            'key'         => $this->payload['key'],
            'key_type'    => $this->payload['key_type'],
            'description' => $this->payload['description'] ?? 'Saque solicitado',
        ];

        try {
            $resp = $provider->withdraw(
                $domainPayload['amount'],
                $domainPayload
            );
        } catch (Throwable $e) {

            Log::error('[ProcessWithdrawJob][XFLOW] ❌ Erro ao chamar provider', [
                'withdraw_id' => $withdraw->id,
                'error'       => $e->getMessage(),
                'payload'     => $domainPayload,
            ]);

            $withdrawService->refundLocal(
                $withdraw,
                'Erro ao criar saque na XFlow: ' . $e->getMessage()
            );
            return;
        }

        /**
         * ✅ LEITURA CORRETA DO RETORNO DA XFLOW
         */
        $providerId = data_get($resp, 'withdrawal.transaction_id');
        $providerStatus = strtolower(
            data_get($resp, 'withdrawal.status', 'pending')
        );

        Log::info('[ProcessWithdrawJob][XFLOW] 🔁 Retorno provider', [
            'withdraw_id'     => $withdraw->id,
            'provider_id'     => $providerId,
            'provider_status' => $providerStatus,
        ]);

        if (! $providerId) {
            Log::error('[ProcessWithdrawJob][XFLOW] ❌ transaction_id não retornado', [
                'withdraw_id' => $withdraw->id,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal(
                $withdraw,
                'XFlow não retornou transaction_id'
            );
            return;
        }

        /**
         * ✅ SALVA VÍNCULO DEFINITIVO
         */
        $withdrawService->updateProviderReference(
            $withdraw,
            $providerId,
            $providerStatus,
            $resp
        );
    }
}
