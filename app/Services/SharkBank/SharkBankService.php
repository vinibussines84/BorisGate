<?php

namespace App\Services\SharkBank;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SharkBankService
{
    protected string $baseUrl;
    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl   = config('services.sharkbank.url');
        $this->secretKey = config('services.sharkbank.secret');
    }

    /**
     * 🚀 Cria uma transação PIX via SharkBank
     */
    public function createPixTransaction(array $payload): array
    {
        try {
            $url = "{$this->baseUrl}/v1/transactions";

            $response = Http::withHeaders([
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$this->secretKey}",
            ])->post($url, $payload);

            Log::info('SHARKBANK_CREATE_PIX', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return [
                'status' => $response->status(),
                'body'   => $response->json(),
            ];

        } catch (\Throwable $e) {
            Log::error('SHARKBANK_CREATE_PIX_ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 500,
                'body'   => ['error' => 'Failed to create SharkBank PIX transaction'],
            ];
        }
    }

    /**
     * 🔍 Consulta uma transação por ID
     */
    public function getTransaction(string $transactionId): array
    {
        try {
            $url = "{$this->baseUrl}/v1/transactions/{$transactionId}";

            $response = Http::withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => "Bearer {$this->secretKey}",
            ])->get($url);

            return [
                'status' => $response->status(),
                'body'   => $response->json(),
            ];

        } catch (\Throwable $e) {
            Log::error('SHARKBANK_GET_TRANSACTION_ERROR', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'body'   => ['error' => 'Failed to fetch transaction'],
            ];
        }
    }

    /**
     * 💰 Consulta saldo disponível
     */
    public function getBalance(): array
    {
        try {
            $url = "{$this->baseUrl}/v1/balance/available";

            $response = Http::withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => "Bearer {$this->secretKey}",
            ])->get($url);

            return [
                'status' => $response->status(),
                'body'   => $response->json(),
            ];

        } catch (\Throwable $e) {
            Log::error('SHARKBANK_GET_BALANCE_ERROR', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'body'   => ['error' => 'Failed to fetch balance'],
            ];
        }
    }
}
