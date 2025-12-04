<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Log;
use Exception;

class ProviderService
{
    protected $provider;

    public function __construct()
    {
        $this->provider = $this->resolveProvider();
    }

    protected function resolveProvider()
    {
        try {

            // 🔥 Agora usa a Pluggou
            return new ProviderPluggou();

        } catch (\Throwable $e) {
            Log::error("PROVIDER_INIT_FAILED", [
                'error' => $e->getMessage(),
            ]);
            throw new Exception("Falha ao inicializar o provedor de pagamentos.");
        }
    }

    public function createPix(float $amount, array $payer)
    {
        try {
            return $this->provider->createPix($amount, $payer);

        } catch (\Throwable $e) {

            Log::error("PROVIDER_CREATE_PIX_FAILED", [
                'error'  => $e->getMessage(),
                'amount' => $amount,
                'payer'  => $payer,
            ]);

            throw new Exception("Erro ao criar transação PIX no provedor.");
        }
    }

    public function getTransactionStatus(string $transactionId)
    {
        try {
            return $this->provider->getTransactionStatus($transactionId);

        } catch (\Throwable $e) {

            Log::error("PROVIDER_GET_STATUS_FAILED", [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            throw new Exception("Erro ao consultar status da transação.");
        }
    }

    public function withdraw(float $amount, array $recipient)
    {
        try {
            return $this->provider->withdraw($amount, $recipient);

        } catch (\Throwable $e) {

            Log::error("PROVIDER_WITHDRAW_FAILED", [
                'error'     => $e->getMessage(),
                'amount'    => $amount,
                'recipient' => $recipient,
            ]);

            throw new Exception("Erro ao processar saque no provedor.");
        }
    }

    public function processWebhook(array $payload)
    {
        try {
            return $this->provider->processWebhook($payload);

        } catch (\Throwable $e) {

            Log::error("PROVIDER_WEBHOOK_FAILED", [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);

            throw new Exception("Erro ao processar webhook do provedor.");
        }
    }
}
