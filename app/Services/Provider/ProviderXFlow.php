<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProviderXFlow
{
    protected string $baseUrl = "https://api.xflowpayments.co";
    protected ?string $token = null;

    public function __construct()
    {
        $this->authenticate();
    }

    /**
     * 🔐 Autenticação para gerar token JWT
     */
    private function authenticate()
    {
        $response = Http::post("{$this->baseUrl}/api/auth/login", [
            "client_id"     => env("XFLOW_CLIENT_ID"),
            "client_secret" => env("XFLOW_CLIENT_SECRET"),
        ]);

        if ($response->failed()) {
            Log::error("XFLOW_AUTH_FAILED", [
                "response" => $response->body(),
            ]);
            throw new Exception("Falha na autenticação XFlow.");
        }

        $this->token = $response->json("token");

        if (!$this->token) {
            throw new Exception("Token inválido retornado pela XFlow.");
        }
    }

    /**
     * 🧾 Criar PIX (gera QRCode)
     */
    public function createPix(float $amount, array $payer)
    {
        $payload = [
            "amount" => $amount,
            "external_id" => "ext_" . uniqid(),
            "clientCallbackUrl" => route("webhook.xflow"),
            "payer" => [
                "name"     => $payer["name"] ?? "Cliente",
                "email"    => $payer["email"] ?? null,
                "document" => $payer["document"] ?? null,
            ],
        ];

        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/api/payments/deposit", $payload);

        if ($response->failed()) {
            Log::error("XFLOW_CREATE_PIX_FAILED", [
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
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/api/payments/{$transactionId}");

        if ($response->failed()) {
            Log::error("XFLOW_STATUS_FAILED", [
                "transaction_id" => $transactionId,
                "response" => $response->body(),
            ]);
            throw new Exception("Erro ao consultar status da XFlow.");
        }

        return $response->json();
    }

    /**
     * 💸 Saque (não existe no trecho, mas deixei pronto)
     */
    public function withdraw(float $amount, array $recipient)
    {
        throw new Exception("Withdraw ainda não implementado na XFlow.");
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
}
