<?php

namespace App\Jobs;

use App\Models\Withdraw;
use App\Services\Provider\ProviderGetPayOut;
use App\Services\Withdraw\WithdrawService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        ProviderGetPayOut $provider,
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

        Log::info('[ProcessWithdrawJob] 🚀 Iniciando processamento (GetPay)', [
            'withdraw_id' => $withdraw->id,
            'payload'     => $this->payload,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2) Evitar reprocessar saque já finalizado
        |--------------------------------------------------------------------------
        */
        if (in_array($withdraw->status, ['paid', 'failed'], true)) {
            Log::warning('[ProcessWithdrawJob] ⚠️ Ignorado — saque já finalizado', [
                'withdraw_id' => $withdraw->id,
                'status'      => $withdraw->status,
            ]);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Chamar provider GetPay
        |--------------------------------------------------------------------------
        */
        try {
            $resp = $provider->createWithdrawal($this->payload);
        } catch (\Throwable $e) {
            Log::error('[ProcessWithdrawJob] 💥 Erro ao chamar GetPay', [
                'withdraw_id' => $withdraw->id,
                'exception'   => $e->getMessage(),
            ]);

            // Falha → estorna local
            $withdrawService->refundLocal(
                $withdraw,
                'Erro ao comunicar com o provider: ' . $e->getMessage()
            );
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 4) Validar resposta
        |--------------------------------------------------------------------------
        */
        if (!isset($resp['success']) || $resp['success'] !== true) {

            $reason = $resp['message'] ?? 'Erro desconhecido do provider';

            Log::error('[ProcessWithdrawJob] ❌ Falha provider', [
                'withdraw_id' => $withdraw->id,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($withdraw, $reason);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 5) Capturar provider_id (UUID)
        |--------------------------------------------------------------------------
        */
        $providerId =
            data_get($resp, 'data.uuid')
            ?? data_get($resp, 'data.id')
            ?? data_get($resp, 'id');

        if (!$providerId) {
            Log::error('[ProcessWithdrawJob] ⚠️ provider_id ausente', [
                'withdraw_id' => $withdraw->id,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($withdraw, 'missing_provider_id');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 6) Registrar provider_reference
        |--------------------------------------------------------------------------
        */
        $withdrawService->updateProviderReference(
            $withdraw,
            $providerId,
            'processing',
            $resp
        );

        Log::info('[ProcessWithdrawJob] 🔁 Saque enviado ao provider', [
            'withdraw_id' => $withdraw->id,
            'provider_id' => $providerId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 7) Status imediato retornado pela GetPay
        |--------------------------------------------------------------------------
        */
        $providerStatus = strtolower(data_get($resp, 'data.status', 'pending'));

        if (in_array($providerStatus, ['paid', 'success', 'completed'])) {

            // Pago imediato — finalizar
            $withdrawService->markAsPaid($withdraw, $resp);

            Log::info('[ProcessWithdrawJob] ✅ Pago imediatamente no retorno', [
                'withdraw_id' => $withdraw->id,
                'provider_id' => $providerId,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 8) Caso pending/processing → aguarda webhook
        |--------------------------------------------------------------------------
        */
        Log::info('[ProcessWithdrawJob] 🕒 Aguardando webhook (GetPay)…', [
            'withdraw_id'     => $withdraw->id,
            'provider_status' => $providerStatus,
        ]);
    }
}
