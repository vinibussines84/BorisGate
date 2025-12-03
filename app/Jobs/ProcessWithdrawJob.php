<?php

namespace App\Jobs;

use App\Models\Withdraw;
use App\Services\PodPay\PodPayCashoutService;
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

    /**
     * IMPORTANTE:
     * Nunca serialize o modelo inteiro.
     * Sempre armazene o ID do withdraw.
     */
    public int $withdrawId;
    public array $payload;

    /**
     * Tenta até 5x, timeout de 30s
     */
    public $tries   = 5;
    public $timeout = 30;

    public function __construct(Withdraw $withdraw, array $payload)
    {
        $this->withdrawId = $withdraw->id;
        $this->payload    = $payload;

        // Garantir que SEMPRE vai para a fila withdraws
        $this->onQueue('withdraws');
    }

    public function handle(
        PodPayCashoutService $podpay,
        WithdrawService $withdrawService
    ) {
        /*
        |--------------------------------------------------------------------------
        | 0) Buscar o withdraw
        |--------------------------------------------------------------------------
        */
        $withdraw = Withdraw::find($this->withdrawId);

        if (!$withdraw) {
            Log::error('[ProcessWithdrawJob] Withdraw não encontrado', [
                'id' => $this->withdrawId,
            ]);
            return;
        }

        Log::info('[ProcessWithdrawJob] Iniciando job (PodPay)', [
            'withdraw_id' => $withdraw->id,
            'payload'     => $this->payload,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 1) Prevenção de reprocessamento
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
        | 2) Enviar saque para PodPay
        |--------------------------------------------------------------------------
        */
        $resp = $podpay->createWithdrawal($this->payload);

        if (!$resp['success']) {

            $reason = $resp['response']['message']
                ?? $resp['exception']
                ?? 'Erro PodPay Cashout';

            Log::error('[ProcessWithdrawJob] Erro retornado pela PodPay', [
                'withdraw_id' => $withdraw->id,
                'reason'      => $reason,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($withdraw, $reason);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Capturar provider_reference (ID da PodPay)
        |--------------------------------------------------------------------------
        */
        $providerId = data_get($resp, 'data.id');

        if (!$providerId) {
            Log::error('[ProcessWithdrawJob] provider_id ausente no retorno PodPay', [
                'withdraw_id' => $withdraw->id,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($withdraw, 'missing_provider_id');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 4) Mapear status PodPay → status interno
        |--------------------------------------------------------------------------
        */
        $providerStatus = strtoupper(data_get($resp, 'data.status', 'PROCESSING'));

        $status = match ($providerStatus) {
            'COMPLETED'        => 'paid',
            'CANCELLED',
            'REFUSED'          => 'failed',
            'PROCESSING',
            'PENDING_QUEUE',
            'PENDING_ANALYSIS' => 'processing',
            default            => 'processing',
        };

        /*
        |--------------------------------------------------------------------------
        | 5) Atualizar saque local
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
        | 6) Finalizar caso esteja pago
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
