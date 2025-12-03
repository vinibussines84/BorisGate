<?php

namespace App\Jobs;

use App\Models\Withdraw;
use App\Models\User;
use App\Services\Pluggou\PluggouWithdrawService;
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

        $this->onQueue('withdraws'); // ✔ Ideal para Horizon
    }

    public function handle(
        PluggouWithdrawService $pluggou,
        WithdrawService $withdrawService
    ) {
        Log::info('[ProcessWithdrawJob] Iniciando job', [
            'withdraw_id' => $this->withdraw->id,
            'payload'     => $this->payload,
        ]);

        // Verifica se já está finalizado
        if (in_array($this->withdraw->status, ['paid','failed'], true)) {
            Log::warning('[ProcessWithdrawJob] Ignorado — saque já finalizado', [
                'id'     => $this->withdraw->id,
                'status' => $this->withdraw->status,
            ]);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 1) Enviar para Pluggou
        |--------------------------------------------------------------------------
        */
        $resp = $pluggou->createWithdrawal($this->payload);

        if (!$resp['success']) {

            $reason = $resp['data']['message']
                ?? ($resp['validation_errors'] ?? null)
                ?? "Erro ao criar saque na Pluggou";

            Log::error('[ProcessWithdrawJob] Falha ao enviar saque p/ Pluggou', [
                'withdraw_id' => $this->withdraw->id,
                'reason'      => $reason,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($this->withdraw, $reason);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 2) Extrair o provider_reference
        |--------------------------------------------------------------------------
        */
        $providerRef = data_get($resp, 'data.data.id');

        if (!$providerRef) {
            Log::error('[ProcessWithdrawJob] Sem provider_reference', [
                'withdraw_id' => $this->withdraw->id,
                'response'    => $resp,
            ]);

            $withdrawService->refundLocal($this->withdraw, 'missing_provider_id');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Atualizar o withdraw local com a referência
        |--------------------------------------------------------------------------
        */
        $providerStatus = strtolower(data_get($resp, 'data.data.status', 'processing'));

        $status = match ($providerStatus) {
            'paid','success','completed' => 'paid',
            'failed','error','canceled','cancelled' => 'failed',
            default => 'processing',
        };

        $withdrawService->updateProviderReference(
            $this->withdraw,
            $providerRef,
            $status,
            $resp
        );

        /*
        |--------------------------------------------------------------------------
        | 4) Se o saque já vier como PAGO, finaliza agora mesmo
        |--------------------------------------------------------------------------
        */
        if ($status === 'paid') {

            Log::info('[ProcessWithdrawJob] Saque aprovado imediatamente', [
                'withdraw_id' => $this->withdraw->id,
            ]);

            $withdrawService->markAsPaid($this->withdraw, $resp);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 5) Caso contrário, aguarda o webhook da Pluggou
        |--------------------------------------------------------------------------
        */
        Log::info('[ProcessWithdrawJob] Saque enviado e aguardando webhook', [
            'withdraw_id' => $this->withdraw->id,
            'provider_ref'=> $providerRef,
            'status'      => $status,
        ]);
    }
}
