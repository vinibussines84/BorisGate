<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProviderXFlow
{
    protected string $baseUrl;
    protected string $token;
    protected int $timeout;

    public function __construct()
    {
        // 🔥 Carregar valores do config/xflow.php
        $this->token   = config("xflow.token");
        $this->baseUrl = config("xflow.base_url", "https://api.xflowpayments.co");
        $this->timeout = config("xflow.timeout", 15);

        if (!$this->token) {
            Log::error("XFLOW_TOKEN_MISSING", [
                "token" => $this->token
            ]);
            throw new Exception("Token XFlow ausente ou inválido. Configure XFLOW_TOKEN no .env.");
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
            ->timeout($this->timeout)
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

        $response = Http::withToken($this->token)
            ->timeout($this->timeout)
            ->get($url);

        if ($response->failed()) {
            Log::error("XFLOW_STATUS_FAILED", [
                "transaction_id" => $transactionId,
                "status"         => $response->status(),
                "response"       => $response->body(),
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
            "status"   => "ok",
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
