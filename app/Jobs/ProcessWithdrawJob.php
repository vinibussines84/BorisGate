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

    /**
     * Tentará até 5 vezes antes de falhar.
     */
    public $tries   = 5;
    public $timeout = 30;

    public function __construct(Withdraw $withdraw, array $payload)
    {
        $this->withdrawId = $withdraw->id;
        $this->payload    = $payload;

        // Envia para fila específica
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
            Log::error('[ProcessWithdrawJob] Withdraw não encontrado', [
                'id' => $this->withdrawId,
            ]);
            return;
        }

        Log::info('[ProcessWithdrawJob] Iniciando job (Pluggou)', [
            'withdraw_id' => $withdraw->id,
            'payload'     => $this->payload,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2) Evitar reprocessamento
        |--------------------------------------------------------------------------
        */
        if (in_array($withdraw->status, ['paid', 'failed'], true)) {
            Log::warning('[ProcessWithdrawJob] Ignorando job: saque já finalizado', [
                'withdraw_id' => $withdraw->id,
                'status'      => $withdraw->status,
            ]);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Enviar saque para Pluggou
        |--------------------------------------------------------------------------
        */
        try {
            $resp = $pluggou->createCashout($this->payload);
        } catch (\Throwable $e) {
            Log::error('[ProcessWithdrawJob] Erro ao chamar Pluggou API', [
                'withdraw_id' => $withdraw->id,
                'error'       => $e->getMessage(),
            ]);

            $withdrawService->refundLocal($withdraw, $e->getMessage());
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 4) Validar resposta
        |--------------------------------------------------------------------------
        */
        if (!isset($resp['success']) || $resp['success'] === false) {
            $reason = $resp['message'] ?? 'Erro desconhecido na Pluggou';
            Log::error('[ProcessWithdrawJob] Falha no cashout', [
                'withdraw_id' => $withdraw->id,
                'reason'      => $reason,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($withdraw, $reason);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 5) Capturar ID da transação
        |--------------------------------------------------------------------------
        */
        $providerId = data_get($resp, 'data.id') ?? data_get($resp, 'id');
        if (!$providerId) {
            Log::error('[ProcessWithdrawJob] ID da transação ausente no retorno Pluggou', [
                'withdraw_id' => $withdraw->id,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($withdraw, 'missing_provider_id');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 6) Mapear status Pluggou → interno
        |--------------------------------------------------------------------------
        */
        $providerStatus = strtoupper(data_get($resp, 'data.status', 'PROCESSING'));
        $status = match ($providerStatus) {
            'PAID', 'COMPLETED' => 'paid',
            'FAILED', 'CANCELLED', 'ERROR' => 'failed',
            default => 'processing',
        };

        /*
        |--------------------------------------------------------------------------
        | 7) Atualizar saque local
        |--------------------------------------------------------------------------
        */
        $withdrawService->updateProviderReference(
            $withdraw,
            $providerId,
            $status,
            $resp
        );

        /*
        |--------------------------------------------------------------------------
        | 8) Marcar como pago se concluído
        |--------------------------------------------------------------------------
        */
        if ($status === 'paid') {
            $withdrawService->markAsPaid($withdraw, $resp);

            Log::info('[ProcessWithdrawJob] Saque concluído com sucesso!', [
                'withdraw_id'   => $withdraw->id,
                'provider_id'   => $providerId,
                'provider_resp' => $resp,
            ]);
        }
    }
}
