<?php

namespace App\Services\PodPay;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PodPayCashoutService
{
    private string $baseUrl;
    private string $withdrawKey;

    public function __construct()
    {
        $this->baseUrl     = config('services.podpay.url', 'https://api.podpay.co/v1');
        $this->withdrawKey = config('services.podpay.withdraw_key');
    }

    /**
     * Criar um saque via API da PodPay
     */
    public function createWithdrawal(array $payload): array
    {
        try {
            $response = Http::withHeaders([
                'x-withdraw-key' => $this->withdrawKey,
                'Accept'         => 'application/json',
            ])
            ->post($this->baseUrl . '/transfers', $payload);

            $json = $response->json();

            if ($response->failed()) {
                Log::error('❌ PodPay: erro ao criar saque', [
                    'payload' => $payload,
                    'response' => $json,
                ]);

                return [
                    'success' => false,
                    'response' => $json,
                    'http_code' => $response->status(),
                ];
            }

            return [
                'success' => true,
                'data'    => $json,
                'http_code' => $response->status(),
            ];

        } catch (\Throwable $e) {

            Log::error('🚨 PodPay: exceção ao criar saque', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'exception' => $e->getMessage(),
            ];
        }
    }
}
