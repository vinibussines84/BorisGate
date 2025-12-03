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

    public $tries   = 5;
    public $timeout = 30;

    private Withdraw $withdraw;
    private array $payload;

    public function __construct(Withdraw $withdraw, array $payload)
    {
        $this->withdraw = $withdraw;
        $this->payload  = $payload;
        $this->onQueue('withdraws');
    }

    public function handle(
        PodPayCashoutService $podpay,
        WithdrawService $withdrawService
    ) {
        Log::info('[ProcessWithdrawJob] Iniciando job (PodPay)', [
            'withdraw_id' => $this->withdraw->id,
            'payload'     => $this->payload,
        ]);

        // Se já finalizado → ignora
        if (in_array($this->withdraw->status, ['paid', 'failed'], true)) {
            Log::warning('[ProcessWithdrawJob] Ignorado — saque já finalizado', [
                'id'     => $this->withdraw->id,
                'status' => $this->withdraw->status,
            ]);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 1) Enviar saque para a PodPay
        |--------------------------------------------------------------------------
        */
        $resp = $podpay->createWithdrawal($this->payload);

        if (!$resp['success']) {

            $reason = $resp['response']['message']
                ?? $resp['exception']
                ?? 'Erro PodPay Cashout';

            Log::error('[ProcessWithdrawJob] Falha no cashout PodPay', [
                'withdraw_id' => $this->withdraw->id,
                'reason'      => $reason,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($this->withdraw, $reason);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 2) Obter provider_reference
        |--------------------------------------------------------------------------
        */
        $providerId = data_get($resp, 'data.id');

        if (!$providerId) {
            Log::error('[ProcessWithdrawJob] Sem provider_reference', [
                'withdraw_id' => $this->withdraw->id,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($this->withdraw, 'missing_provider_id');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Mapear status PodPay
        |--------------------------------------------------------------------------
        */
        $providerStatusRaw = data_get($resp, 'data.status', 'PROCESSING');
        $providerStatus = strtoupper(trim($providerStatusRaw));

        Log::info('[ProcessWithdrawJob] Status recebido da PodPay', [
            'withdraw_id' => $this->withdraw->id,
            'provider_status_raw' => $providerStatusRaw
        ]);

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
        | 4) Atualizar withdrawal local
        |--------------------------------------------------------------------------
        */
        $withdrawService->updateProviderReference(
            $this->withdraw,
            $providerId,
            $status,
            $resp
        );

        /*
        |--------------------------------------------------------------------------
        | 5) Se já vier pago → finaliza imediatamente
        |--------------------------------------------------------------------------
        */
        if ($status === 'paid') {

            Log::info('[ProcessWithdrawJob] Saque pago imediatamente (PodPay)', [
                'withdraw_id' => $this->withdraw->id,
            ]);

            $withdrawService->markAsPaid($this->withdraw, $resp);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 6) Caso contrário aguarda webhook
        |--------------------------------------------------------------------------
        */
        Log::info('[ProcessWithdrawJob] Saque enviado e aguardando webhook PodPay', [
            'withdraw_id' => $this->withdraw->id,
            'provider_ref'=> $providerId,
            'status'      => $status,
        ]);
    }
}
