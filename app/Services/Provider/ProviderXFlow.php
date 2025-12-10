<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class ProviderXFlow
{
    protected string $baseUrl;
    protected string $token;
    protected int $timeout;
    protected string $callbackUrl;

    public function __construct()
    {
        // 🔥 Carrega tudo do config/xflow.php
        $this->token       = config("xflow.token");
        $this->baseUrl     = config("xflow.base_url", "https://api.xflowpayments.co");
        $this->timeout     = config("xflow.timeout", 8); // 🔥 timeout menor e mais inteligente
        $this->callbackUrl = config("xflow.callback_url", "https://equitpay.app/api/webhooks/xflow");

        if (!$this->token) {
            Log::error("XFLOW_TOKEN_MISSING", [
                "token" => $this->token
            ]);
            throw new Exception("Token XFlow ausente ou inválido. Configure XFLOW_TOKEN no .env.");
        }
    }

    /**
     * 🧾 Criar PIX (retorna QRCode)
     */
    public function createPix(float $amount, array $data)
    {
        // 🔥 Corrige o formato do payload para PAYER (padrão XFlow)
        $payload = [
            "amount"         => $amount,

            // Muito mais rápido que uniqid()
            "external_id"    => $data["external_id"] ?? (string) Str::orderedUuid(),

            // Não usa route() dentro do provider → muito mais rápido
            "clientCallbackUrl" => $data["clientCallbackUrl"] ?? $this->callbackUrl,

            "payer" => [
                "name"     => $data["payer"]["name"]     ?? $data["name"]     ?? "Cliente",
                "email"    => $data["payer"]["email"]    ?? $data["email"]    ?? "cliente@example.com",
                "document" => $data["payer"]["document"] ?? $data["document"] ?? null,
            ],
        ];

        // 🔥 Evita log pesado em produção
        if (app()->environment("local")) {
            Log::info("XFLOW_CREATE_PIX_REQUEST", $payload);
        }

        // 🚀 Conexão otimizada com keep-alive + timeout curto
        $response = Http::withToken($this->token)
            ->withHeaders([
                "Connection" => "keep-alive"
            ])
            ->timeout($this->timeout)
            ->retry(2, 150) // 🔥 Retentativa rápida (evita picos de latência)
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
            ->withHeaders([
                "Connection" => "keep-alive"
            ])
            ->timeout($this->timeout)
            ->retry(2, 150)
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
