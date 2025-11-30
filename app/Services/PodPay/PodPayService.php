<?php

namespace App\Services\PodPay;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PodPayService
{
    private string $baseUrl;
    private string $publicKey;
    private string $secretKey;

    public function __construct()
    {
        $this->baseUrl   = "https://api.podpay.co/v1";

        // Pegando via config (correto para produção)
        $this->publicKey = config('podpay.public_key', '');
        $this->secretKey = config('podpay.secret_key', '');

        // Segurança: validar antes de continuar
        if (empty($this->publicKey) || empty($this->secretKey)) {
            Log::critical("❌ PodPay keys are missing. Check your .env or config/podpay.php.");
        }
    }

    /**
     * 📌 Autenticação Basic
     */
    private function authHeader(): string
    {
        return "Basic " . base64_encode("{$this->publicKey}:{$this->secretKey}");
    }

    /**
     * 🧾 Criação de transação PIX
     */
    public function createPixTransaction(array $payload): array
    {
        try {
            $response = Http::withHeaders([
                "Authorization" => $this->authHeader(),
                "Accept"        => "application/json",
                "Content-Type"  => "application/json",
            ])
            ->timeout(20)
            ->post("{$this->baseUrl}/transactions", $payload);

            return [
                "status" => $response->status(),
                "body"   => $response->json(),
            ];

        } catch (\Throwable $e) {

            Log::error("PODPAY_PIX_CREATE_ERROR", [
                "exception" => $e->getMessage(),
                "payload"   => $payload
            ]);

            return [
                "status" => 500,
                "body"   => ["error" => $e->getMessage()],
            ];
        }
    }

    /**
     * 🔎 Consultar transação por ID
     */
    public function getTransaction(string $id): array
    {
        try {
            $response = Http::withHeaders([
                "Authorization" => $this->authHeader(),
                "Accept"        => "application/json",
            ])
            ->timeout(20)
            ->get("{$this->baseUrl}/transactions/{$id}");

            return [
                "status" => $response->status(),
                "body"   => $response->json(),
            ];

        } catch (\Throwable $e) {

            Log::error("PODPAY_GET_TRANSACTION_ERROR", [
                "exception" => $e->getMessage(),
                "id"        => $id,
            ]);

            return [
                "status" => 500,
                "body"   => ["error" => $e->getMessage()],
            ];
        }
    }
}
