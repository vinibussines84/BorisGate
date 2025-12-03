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

        if (in_array($this->withdraw->status, ['paid', 'failed'], true)) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 1) Envia saque para a PodPay
        |--------------------------------------------------------------------------
        */
        $resp = $podpay->createWithdrawal($this->payload);

        if (!$resp['success']) {

            $reason = $resp['response']['message']
                ?? $resp['exception']
                ?? 'Erro PodPay Cashout';

            $withdrawService->refundLocal($this->withdraw, $reason);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 2) provider_reference = id da PodPay
        |--------------------------------------------------------------------------
        */
        $providerId = data_get($resp, 'data.id');

        if (!$providerId) {
            $withdrawService->refundLocal($this->withdraw, 'missing_provider_id');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Mapear status PodPay
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
        | 4) Atualizar withdraw local
        |--------------------------------------------------------------------------
        */
        $withdrawService->updateProviderReference(
            $this->withdraw,
            $providerId,
            $status,
            $resp
        );

        if ($status === 'paid') {
            $withdrawService->markAsPaid($this->withdraw, $resp);
        }
    }
}
