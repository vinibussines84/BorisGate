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

    // 🔥 NÃO REFAZ automaticamente
    public $tries   = 1;
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
            'withdraw_id' => $withdraw->id,
        ]);

        /**
         * ✔ EVITAR reprocessar se já enviado ao provider
         */
        if (!empty($withdraw->provider_reference)) {
            Log::warning('[ProcessWithdrawJob] ❌ Já tem provider_reference — ignorando');
            return;
        }

        /**
         * ✔ Evitar reprocessar se já finalizado
         */
        if (in_array($withdraw->status, ['paid','failed','canceled'], true)) {
            Log::warning('[ProcessWithdrawJob] ❌ Já finalizado — ignorando');
            return;
        }

        /**
         * ✔ Criar payload para provider
         */
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

            // ⚠️ SE FOR RATE-LIMIT → PERMITIR retry MANUAL
            if ($e->getMessage() === "RATE_LIMIT") {
                Log::warning('[ProcessWithdrawJob] ⏳ Rate limit — tentando novamente em 10s');

                $this->release(10);
                return;
            }

            // ❌ ERRO REAL → NÃO CHAMAR DE NOVO
            Log::error('[ProcessWithdrawJob] ❌ Falha real ao chamar provider', [
                'error' => $e->getMessage(),
            ]);

            $withdrawService->refundLocal($withdraw, "Erro ao criar saque: ".$e->getMessage());
            return;
        }

        /**
         * ✔ Processar resposta
         */
        $withdrawal      = data_get($resp, 'withdrawal');
        $providerId      = data_get($withdrawal, 'id');
        $providerStatus  = strtolower(data_get($withdrawal, 'status', 'pending'));

        Log::info('[ProcessWithdrawJob] 🔁 Retorno provider', [
            'withdraw_id'     => $withdraw->id,
            'provider_id'     => $providerId,
            'provider_status' => $providerStatus,
        ]);

        /**
         * ✔ Converter approved → paid
         */
        if ($providerStatus === 'approved') {
            $providerStatus = 'paid';
        }

        /**
         * ✔ Salvar provider_reference
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
         * ✔ Se for considerado pago → marcar como paid
         */
        if ($providerStatus === 'paid') {
            $withdrawService->markAsPaid(
                $withdraw,
                payload: $resp,
                extra: [
                    'paid_at' => now(),
                    'provider_status' => 'paid'
                ]
            );

            Log::info('[ProcessWithdrawJob] ✅ Saque concluído como PAID');
            return;
        }

        /**
         * ❌ Falha real do provider
         */
        $withdrawService->refundLocal(
            $withdraw,
            "ColdFy retornou status: {$providerStatus}"
        );

        Log::warning('[ProcessWithdrawJob] ❌ Saque marcado como FAILED (provider não aprovou)', [
            'withdraw_id' => $withdraw->id
        ]);
    }
}
