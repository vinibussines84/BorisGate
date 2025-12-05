<?php

namespace App\Jobs;

use App\Models\Withdraw;
use App\Services\Pluggou\PluggouCashoutService;
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
        PluggouCashoutService $pluggou,
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

        Log::info('[ProcessWithdrawJob] 🚀 Iniciando processamento (Pluggou)', [
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
        | 3) Chamar API da Pluggou
        |--------------------------------------------------------------------------
        */
        try {
            $resp = $pluggou->createCashout($this->payload);
        } catch (\Throwable $e) {
            Log::error('[ProcessWithdrawJob] 💥 Erro ao chamar Pluggou', [
                'withdraw_id' => $withdraw->id,
                'exception'   => $e->getMessage(),
            ]);

            // 🔥 Falha antes do envio → estorna
            $withdrawService->refundLocal(
                $withdraw,
                'Erro ao comunicar com a Pluggou: ' . $e->getMessage()
            );
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 4) Validar resposta
        |--------------------------------------------------------------------------
        */
        if (!isset($resp['success']) || $resp['success'] === false) {

            $reason = $resp['message'] ?? 'Erro desconhecido da Pluggou';

            Log::error('[ProcessWithdrawJob] ❌ Falha Pluggou', [
                'withdraw_id' => $withdraw->id,
                'response'    => $resp,
            ]);

            // 🔥 Falha no provider → estorna
            $withdrawService->refundLocal($withdraw, $reason);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 5) Capturar ID do provider
        |--------------------------------------------------------------------------
        */
        $providerId = data_get($resp, 'data.id') ?? data_get($resp, 'id');

        if (!$providerId) {
            Log::error('[ProcessWithdrawJob] ⚠️ provider_id ausente', [
                'withdraw_id' => $withdraw->id,
                'response'    => $resp,
            ]);

            // 🔥 Sem ID → falha
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

        Log::info('[ProcessWithdrawJob] 🔁 Saque enviado à Pluggou', [
            'withdraw_id' => $withdraw->id,
            'provider_id' => $providerId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 7) STATUS retornado pela Pluggou imediatamente
        |--------------------------------------------------------------------------
        */
        $providerStatus = strtolower(data_get($resp, 'data.status', 'processing'));

        if (in_array($providerStatus, ['paid', 'success', 'completed'])) {

            // 🔥 Se já veio pago → concluir imediatamente
            $withdrawService->markAsPaid($withdraw, $resp);

            Log::info('[ProcessWithdrawJob] ✅ Pago imediatamente no retorno', [
                'withdraw_id' => $withdraw->id,
                'provider_id' => $providerId,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 8) STATUS ≠ PAID → NÃO estornar aqui
        |    Aguardar webhook para concluir o saque.
        |--------------------------------------------------------------------------
        */
        Log::info('[ProcessWithdrawJob] 🕒 Aguardando webhook Pluggou…', [
            'withdraw_id' => $withdraw->id,
            'provider_status' => $providerStatus,
        ]);
    }
}
