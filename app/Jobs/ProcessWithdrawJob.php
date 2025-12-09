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
use Exception;

class ProcessWithdrawJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $withdrawId;
    public array $payload;

    public $tries   = 3;
    public $timeout = 120;

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
            Log::error("[WithdrawJob] ❌ Saque não encontrado", [
                'withdraw_id' => $this->withdrawId,
            ]);
            return;
        }

        Log::info("[WithdrawJob] 🚀 Iniciando envio ao ColdFy", [
            'withdraw_id' => $withdraw->id,
            'payload' => $this->payload,
        ]);

        // montar payload para provider
        $providerPayload = [
            'pix_key'      => $this->payload['pixKey'],
            'pix_key_type' => strtolower($this->payload['pixKeyType']),
            'amount'       => $this->payload['amount'],
            'description'  => $this->payload['description'],
        ];

        try {
            $response = $provider->createCashout($providerPayload);
        } catch (Exception $e) {
            $withdrawService->fail($withdraw, "coldfy_error");

            Log::error("[WithdrawJob] ❌ Erro ao enviar ColdFy", [
                'withdraw_id' => $withdraw->id,
                'exception'   => $e->getMessage(),
            ]);

            // estornar saldo
            $withdrawService->refund($withdraw);

            // disparar webhook de falha
            $withdrawService->notifyWebhook($withdraw, 'failed');

            return;
        }

        Log::info("[WithdrawJob] 🔍 Resposta ColdFy", [
            'withdraw_id' => $withdraw->id,
            'response'    => $response,
        ]);

        // Extrair retorno
        $data = data_get($response, 'withdrawal');
        $providerId = data_get($data, 'id');
        $providerStatus = strtolower(data_get($data, 'status', 'pending'));

        // Salvar referência
        $withdrawService->updateProviderReference($withdraw, $providerId, $providerStatus, $response);

        // ---------------------------
        // STATUS FINAL DO SAQUE
        // ---------------------------
        if ($providerStatus === 'approved') {

            $withdrawService->markPaid($withdraw);

            Log::info("[WithdrawJob] ✅ Saque aprovado", [
                'withdraw_id' => $withdraw->id,
                'status' => $providerStatus,
            ]);

            // webhook sucesso
            $withdrawService->notifyWebhook($withdraw, 'paid');
            return;
        }

        // Qualquer outro status = falha
        $withdrawService->fail($withdraw, $providerStatus);
        $withdrawService->refund($withdraw);

        Log::warning("[WithdrawJob] ⚠️ Saque não aprovado", [
            'withdraw_id' => $withdraw->id,
            'status' => $providerStatus,
        ]);

        // webhook falha
        $withdrawService->notifyWebhook($withdraw, 'failed');
    }
}
