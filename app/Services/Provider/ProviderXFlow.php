<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProviderXFlow
{
    protected string $baseUrl = "https://api.xflowpayments.co";
    protected string $token;

    public function __construct()
    {
        // 🔥 Token fixo informado pela XFlow
        $this->token = env("XFLOW_TOKEN");

        if (!$this->token) {
            throw new Exception("Token XFlow não configurado no .env (XFLOW_TOKEN).");
        }
    }

    /**
     * 🧾 Criar PIX (gera QRCode)
     */
    public function createPix(float $amount, array $payer)
    {
        $payload = [
            "amount" => $amount,
            "external_id" => $payer["external_id"] ?? ("ext_" . uniqid()),
            "clientCallbackUrl" => $payer["clientCallbackUrl"] ?? route("webhooks.xflow"),
            "payer" => [
                "name"     => $payer["name"] ?? "Cliente",
                "email"    => $payer["email"] ?? "cliente@example.com",
                "document" => $payer["document"] ?? null,
            ],
        ];

        Log::info("XFLOW_CREATE_PIX_REQUEST", $payload);

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/payments/deposit", $payload);

        if ($response->failed()) {
            Log::error("XFLOW_CREATE_PIX_FAILED", [
                "status"   => $response->status(),
                "response" => $response->body(),
                "payload"  => $payload,
            ]);
            throw new Exception("Erro ao criar PIX na XFlow.");
        }

        return $response->json();
    }

    /**
     * 🔍 Consultar status
     */
    public function getTransactionStatus(string $transactionId)
    {
        $url = "{$this->baseUrl}/api/payments/{$transactionId}";

        $response = Http::withToken($this->token)->get($url);

        if ($response->failed()) {
            Log::error("XFLOW_STATUS_FAILED", [
                "transaction_id" => $transactionId,
                "status" => $response->status(),
                "response" => $response->body(),
            ]);
            throw new Exception("Erro ao consultar status da XFlow.");
        }

        return $response->json();
    }

    /**
     * 📩 Processar webhook
     */
    public function processWebhook(array $payload)
    {
        Log::info("XFLOW_WEBHOOK_RECEIVED", $payload);

        return [
            "status" => "ok",
            "received" => $payload,
        ];
    }

    /**
     * 💸 Saque (placeholder)
     */
    public function withdraw(float $amount, array $recipient)
    {
        throw new Exception("Withdraw ainda não implementado na XFlow.");
    }
}
