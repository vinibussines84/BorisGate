<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProviderPluggou
{
    private string $apiUrl;
    private string $publicKey;
    private string $secretKey;

    public function __construct()
    {
        $this->apiUrl    = config('services.pluggou.api_url', 'https://api.pluggoutech.com/api');
        $this->publicKey = config('services.pluggou.public_key');
        $this->secretKey = config('services.pluggou.secret_key');

        if (!$this->publicKey || !$this->secretKey) {
            throw new Exception("Credenciais da Pluggou não configuradas.");
        }
    }

    /**
     * Criar PIX (cash-in)
     */
    public function createPix(float $amount, array $payer)
    {
        try {
            $payload = [
                "payment_method" => "pix",
                "amount"         => intval($amount * 100), // R$ → centavos
                "buyer" => [
                    "buyer_name"     => $payer["name"] ?? null,
                    "buyer_document" => $payer["document"] ?? null,
                    "buyer_phone"    => $payer["phone"] ?? null,
                ],
            ];

            $response = Http::withHeaders([
                "X-Public-Key" => $this->publicKey,
                "X-Secret-Key" => $this->secretKey,
                "Content-Type" => "application/json",
            ])->post("{$this->apiUrl}/transactions", $payload);

            if (!$response->successful()) {
                Log::error("PLUGGOU_CREATE_PIX_HTTP_ERROR", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new Exception("Erro ao criar PIX: {$response->body()}");
            }

            $json = $response->json();

            Log::info("PLUGGOU_CREATE_PIX_SUCCESS", [
                "response" => $json
            ]);

            return $json;

        } catch (\Throwable $e) {
            Log::error("PLUGGOU_CREATE_PIX_FAILED", [
                'error'   => $e->getMessage(),
                'payload' => $payload ?? null,
            ]);
            throw $e;
        }
    }

    /**
     * Consultar status
     */
    public function getTransactionStatus(string $transactionId)
    {
        try {
            $response = Http::withHeaders([
                "X-Public-Key" => $this->publicKey,
                "X-Secret-Key" => $this->secretKey,
            ])->get("{$this->apiUrl}/transactions/{$transactionId}");

            if (!$response->successful()) {
                Log::error("PLUGGOU_STATUS_HTTP_ERROR", [
                    'id'     => $transactionId,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new Exception("Erro ao consultar status: {$response->body()}");
            }

            $json = $response->json();

            Log::info("PLUGGOU_STATUS_SUCCESS", [
                "id"       => $transactionId,
                "response" => $json,
            ]);

            return $json;

        } catch (\Throwable $e) {
            Log::error("PLUGGOU_STATUS_FAILED", [
                'error' => $e->getMessage(),
                'id'    => $transactionId,
            ]);
            throw $e;
        }
    }

    /**
     * Saque PIX (cash-out)
     */
    public function withdraw(float $amount, array $recipient)
    {
        try {
            $payload = [
                "amount"    => intval($amount * 100),
                "recipient" => [
                    "name"        => $recipient["name"] ?? null,
                    "document"    => $recipient["document"] ?? null,
                    "pix_key"     => $recipient["pix_key"] ?? null,
                ],
            ];

            $response = Http::withHeaders([
                "X-Public-Key" => $this->publicKey,
                "X-Secret-Key" => $this->secretKey,
                "Content-Type" => "application/json",
            ])->post("{$this->apiUrl}/withdraw_pix", $payload);

            if (!$response->successful()) {
                Log::error("PLUGGOU_WITHDRAW_HTTP_ERROR", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new Exception("Erro ao realizar saque: {$response->body()}");
            }

            $json = $response->json();

            Log::info("PLUGGOU_WITHDRAW_SUCCESS", [
                "response" => $json
            ]);

            return $json;

        } catch (\Throwable $e) {
            Log::error("PLUGGOU_WITHDRAW_FAILED", [
                'error' => $e->getMessage(),
                'payload' => $payload ?? null,
            ]);
            throw $e;
        }
    }

    /**
     * Webhook recebido da Pluggou
     */
    public function processWebhook(array $payload)
    {
        try {
            Log::info("PLUGGOU_WEBHOOK_RECEIVED", [
                "payload" => $payload
            ]);

            // Aqui você implementa sua lógica:
            // - atualizar transação no banco
            // - validar assinatura se necessário
            // - salvar logs
            // - etc

            return true;

        } catch (\Throwable $e) {
            Log::error("PLUGGOU_WEBHOOK_FAILED", [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        }
    }
}
