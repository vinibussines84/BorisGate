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

        $this->publicKey = config('podpay.public_key', '');
        $this->secretKey = config('podpay.secret_key', '');

        if (empty($this->publicKey) || empty($this->secretKey)) {
            Log::critical("❌ PodPay keys missing. Configure podpay.public_key and podpay.secret_key.");
        }
    }

    /**
     * Basic auth header
     */
    private function authHeader(): string
    {
        return "Basic " . base64_encode("{$this->publicKey}:{$this->secretKey}");
    }

    /**
     * 🔥 SAFE JSON decode (evita crash)
     */
    private function safeJson($response)
    {
        try {
            return $response->json();
        } catch (\Throwable $e) {
            return ['raw' => $response->body()];
        }
    }

    /**
     * 🧾 Criar transação PIX na PodPay
     */
    public function createPixTransaction(array $payload): array
    {
        try {

            $response = Http::withHeaders([
                    "Authorization" => $this->authHeader(),
                    "Accept"        => "application/json",
                    "Content-Type"  => "application/json",
                ])
                ->timeout(25)
                ->retry(3, 300) // retry inteligente
                ->post("{$this->baseUrl}/transactions", $payload);

            $status = $response->status();
            $json   = $this->safeJson($response);

            Log::info("📤 PODPAY PIX REQUEST", [
                'payload' => $payload,
                'response_status' => $status,
                'response_body'   => $json,
            ]);

            // Se falhou HTTP, retorna erro
            if ($status < 200 || $status >= 300) {
                return [
                    'success' => false,
                    'status'  => $status,
                    'body'    => $json,
                ];
            }

            // Se PodPay retornou JSON sem ID, é erro
            if (!data_get($json, 'id')) {
                return [
                    'success' => false,
                    'status'  => $status,
                    'body'    => $json,
                ];
            }

            return [
                'success' => true,
                'status'  => $status,
                'body'    => $json,
            ];

        } catch (\Throwable $e) {

            Log::error("❌ PODPAY_PIX_CREATE_EXCEPTION", [
                "exception" => $e->getMessage(),
                "payload"   => $payload
            ]);

            return [
                "success" => false,
                "status"  => 500,
                "body"    => ["error" => $e->getMessage()],
            ];
        }
    }

    /**
     * 🔎 Consultar transação Por ID
     */
    public function getTransaction(string $id): array
    {
        try {
            $response = Http::withHeaders([
                    "Authorization" => $this->authHeader(),
                    "Accept"        => "application/json",
                ])
                ->timeout(20)
                ->retry(3, 300)
                ->get("{$this->baseUrl}/transactions/{$id}");

            $status = $response->status();
            $json   = $this->safeJson($response);

            Log::info("📥 PODPAY GET TRANSACTION", [
                'id'               => $id,
                'response_status'  => $status,
                'response_body'    => $json,
            ]);

            return [
                'success' => ($status >= 200 && $status < 300),
                'status'  => $status,
                'body'    => $json,
            ];

        } catch (\Throwable $e) {

            Log::error("❌ PODPAY_GET_TRANSACTION_EXCEPTION", [
                "id"        => $id,
                "exception" => $e->getMessage(),
            ]);

            return [
                "success" => false,
                "status"  => 500,
                "body"    => ["error" => $e->getMessage()],
            ];
        }
    }
}
