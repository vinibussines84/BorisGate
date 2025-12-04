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
        | 1) Buscar o saque no banco
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
        | 2) Evitar reprocessamento de saque finalizado
        |--------------------------------------------------------------------------
        */
        if (in_array($withdraw->status, ['paid', 'failed'], true)) {
            Log::warning('[ProcessWithdrawJob] ⚠️ Ignorando: saque já finalizado', [
                'withdraw_id' => $withdraw->id,
                'status'      => $withdraw->status,
            ]);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Enviar requisição à Pluggou
        |--------------------------------------------------------------------------
        */
        try {
            $resp = $pluggou->createCashout($this->payload);
        } catch (\Throwable $e) {
            Log::error('[ProcessWithdrawJob] 💥 Erro ao chamar API Pluggou', [
                'withdraw_id' => $withdraw->id,
                'exception'   => $e->getMessage(),
            ]);

            $withdrawService->refundLocal($withdraw, 'Erro ao comunicar com a Pluggou: ' . $e->getMessage());
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 4) Validar resposta da Pluggou
        |--------------------------------------------------------------------------
        */
        if (!isset($resp['success']) || $resp['success'] === false) {
            $reason = $resp['message'] ?? 'Erro desconhecido retornado pela Pluggou';

            Log::error('[ProcessWithdrawJob] ❌ Falha no cashout Pluggou', [
                'withdraw_id' => $withdraw->id,
                'reason'      => $reason,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($withdraw, $reason);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 5) Capturar o ID da transação retornado pela Pluggou
        |--------------------------------------------------------------------------
        */
        $providerId = data_get($resp, 'data.id') ?? data_get($resp, 'id');

        if (!$providerId) {
            Log::error('[ProcessWithdrawJob] ⚠️ ID da transação ausente no retorno da Pluggou', [
                'withdraw_id' => $withdraw->id,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($withdraw, 'missing_provider_id');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 6) Mapear status da Pluggou → status interno
        |--------------------------------------------------------------------------
        */
        $providerStatus = strtoupper(data_get($resp, 'data.status', 'PROCESSING'));

        $status = match ($providerStatus) {
            'PAID', 'COMPLETED', 'APPROVED' => 'paid',
            'FAILED', 'CANCELLED', 'ERROR', 'REFUSED' => 'failed',
            'PENDING', 'PROCESSING', 'CREATED' => 'processing',
            default => 'processing',
        };

        /*
        |--------------------------------------------------------------------------
        | 7) Atualizar saque local com dados do provider
        |--------------------------------------------------------------------------
        */
        $withdrawService->updateProviderReference(
            $withdraw,
            $providerId,
            $status,
            $resp
        );

        Log::info('[ProcessWithdrawJob] 🔁 Saque atualizado com status do provider', [
            'withdraw_id' => $withdraw->id,
            'provider_id' => $providerId,
            'status'      => $status,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 8) Se estiver pago, marcar como concluído e disparar webhook
        |--------------------------------------------------------------------------
        */
        if ($status === 'paid') {
            $withdrawService->markAsPaid($withdraw, $resp);

            Log::info('[ProcessWithdrawJob] ✅ Saque concluído com sucesso!', [
                'withdraw_id'   => $withdraw->id,
                'provider_id'   => $providerId,
                'provider_resp' => $resp,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 9) Se falhou, registrar logs (o estorno é feito no webhook/refundLocal)
        |--------------------------------------------------------------------------
        */
        if ($status === 'failed') {
            Log::warning('[ProcessWithdrawJob] 💸 Saque marcado como falhado', [
                'withdraw_id'   => $withdraw->id,
                'provider_id'   => $providerId,
                'provider_resp' => $resp,
            ]);
        }
    }
}
