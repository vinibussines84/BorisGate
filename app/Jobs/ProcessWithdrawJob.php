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
use App\Support\StatusMap;

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
        ProviderColdFyOut $provider,
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

        Log::info('[ProcessWithdrawJob] 🚀 Iniciando processamento (ColdFy)', [
            'withdraw_id' => $withdraw->id,
            'payload'     => $this->payload,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2) Evitar reprocessar saque já finalizado
        |--------------------------------------------------------------------------
        */
        if (in_array($withdraw->status, ['paid', 'failed', 'canceled'], true)) {
            Log::warning('[ProcessWithdrawJob] ⚠️ Ignorado — saque já finalizado', [
                'withdraw_id' => $withdraw->id,
                'status'      => $withdraw->status,
            ]);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Montar payload FINAL padronizado (snake_case)
        |--------------------------------------------------------------------------
        */
        $providerPayload = [
            'external_id'  => $this->payload['externalId'] ?? $this->payload['external_id'],
            'pix_key'      => $this->payload['pixKey'],
            'pix_key_type' => strtolower($this->payload['pixKeyType']),
            'description'  => $this->payload['description'],
            'amount'       => (float) $this->payload['amount'],
        ];

        Log::info('[ProcessWithdrawJob] 🔧 Payload preparado para ColdFy', [
            'withdraw_id' => $withdraw->id,
            'provider_payload' => $providerPayload,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 4) Chamar provider ColdFy
        |--------------------------------------------------------------------------
        */
        try {
            $resp = $provider->createCashout($providerPayload);
        } catch (\Throwable $e) {
            Log::error('[ProcessWithdrawJob] 💥 Erro ao chamar ColdFy', [
                'withdraw_id' => $withdraw->id,
                'exception'   => $e->getMessage(),
            ]);
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 5) Capturar dados do retorno
        |--------------------------------------------------------------------------
        */
        $providerId =
            data_get($resp, 'id') ??
            data_get($resp, 'uuid') ??
            data_get($resp, 'data.id') ??
            null;

        $providerStatus =
            strtolower(data_get($resp, 'status') ?? data_get($resp, 'data.status', 'pending'));

        $normalizedStatus = StatusMap::normalize($providerStatus);

        Log::info('[ProcessWithdrawJob] 🔁 Saque enviado ao provider ColdFy', [
            'withdraw_id'        => $withdraw->id,
            'provider_id'        => $providerId,
            'provider_status'    => $providerStatus,
            'normalized_status'  => $normalizedStatus,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 6) Atualizar referência do provedor
        |--------------------------------------------------------------------------
        */
        if ($providerId) {
            $withdrawService->updateProviderReference(
                $withdraw,
                $providerId,
                $withdraw->status,
                $resp
            );
            $withdraw->refresh();
        }

        /*
        |--------------------------------------------------------------------------
        | 7) Aguardar webhook
        |--------------------------------------------------------------------------
        */
        Log::info('[ProcessWithdrawJob] 🕒 Aguardando webhook (ColdFy)…', [
            'withdraw_id'     => $withdraw->id,
            'provider_status' => $providerStatus,
        ]);
    }
}
