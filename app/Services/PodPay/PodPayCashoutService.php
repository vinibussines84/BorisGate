<?php

namespace App\Services\PodPay;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PodPayCashoutService
{
    private string $baseUrl;
    private string $withdrawKey;
    private string $secretKey;
    private string $publicKey;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl     = config('services.podpay.url', 'https://api.podpay.co/v1');
        $this->withdrawKey = config('services.podpay.withdraw_key');
        $this->secretKey   = config('services.podpay.secret_key');
        $this->publicKey   = config('services.podpay.public_key');
        $this->timeout     = (int) config('services.podpay.timeout', 15);
    }

    /**
     * Gera o token Basic Auth no formato:
     * base64("SECRET_KEY:PUBLIC_KEY")
     */
    private function generateBasicAuth(): string
    {
        return 'Basic ' . base64_encode("{$this->secretKey}:{$this->publicKey}");
    }

    /**
     * Criar um saque via API da PodPay
     */
    public function createWithdrawal(array $payload): array
    {
        try {
            $headers = [
                'Accept'         => 'application/json',
                'Content-Type'   => 'application/json',
                'x-withdraw-key' => $this->withdrawKey,
                'Authorization'  => $this->generateBasicAuth(),
            ];

            Log::info('➡️ Enviando saque para PodPay', [
                'url'     => $this->baseUrl . '/transfers',
                'payload' => $payload,
                'headers' => [
                    'x-withdraw-key' => $headers['x-withdraw-key'],
                    'Authorization'  => '[PROTECTED]',
                ]
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->post($this->baseUrl . '/transfers', $payload);

            $json = $response->json();

            if ($response->failed()) {
                Log::error('❌ PodPay: erro ao criar saque', [
                    'payload'   => $payload,
                    'response'  => $json,
                    'http_code' => $response->status(),
                ]);

                return [
                    'success'   => false,
                    'response'  => $json,
                    'http_code' => $response->status(),
                ];
            }

            return [
                'success'   => true,
                'data'      => $json,
                'http_code' => $response->status(),
            ];

        } catch (\Throwable $e) {

            Log::error('🚨 PodPay: exceção ao criar saque', [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success'   => false,
                'exception' => $e->getMessage(),
            ];
        }
    }
}
