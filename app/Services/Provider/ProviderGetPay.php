<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class ProviderGetPay
{
    protected string $baseUrl = 'https://hub.getpay.one/api';

    /**
     * Obtém o token JWT somente quando necessário.
     */
    protected function getToken(): string
    {
        $cache = Cache::get('getpay_jwt_data');

        // 🟢 Token ainda válido → retorna sem renovar
        if ($cache && isset($cache['token'], $cache['expires_at'])) {

            if (now()->lt($cache['expires_at'])) {
                return $cache['token'];
            }
        }

        // 🔄 Evita múltiplas requisições simultâneas
        return Cache::remember('getpay_jwt_data', 55 * 60, function () {
            return $this->refreshToken();
        })['token'];
    }

    /**
     * 🔐 Requisita novo token à GetPay (somente quando expira)
     */
    private function refreshToken(): array
    {
        Log::info("GETPAY_REFRESH_TOKEN", ['msg' => 'Solicitando novo JWT']);

        $response = Http::post("{$this->baseUrl}/login", [
            'email'    => config('services.getpay.email'),
            'password' => config('services.getpay.password'),
        ]);

        if (!$response->successful() || !$response->json('success')) {
            throw new Exception("Falha ao autenticar na GetPay: " . $response->body());
        }

        $token = $response->json('token');
        $expires = $response->json('expires_at'); // formato: 2025-07-01 00:21:56

        // 🕒 converte para Carbon
        $expiresAt = $expires ? now()->parse($expires) : now()->addMinutes(55);

        $data = [
            'token'      => $token,
            'expires_at' => $expiresAt,
        ];

        Cache::put('getpay_jwt_data', $data, $expiresAt);

        return $data;
    }

    /**
     * Criar PIX (create-payment)
     */
    public function createPix(float $amount, array $payer)
    {
        $token = $this->getToken();

        $payload = [
            'externalId'     => $payer['externalId'],
            'amount'         => $amount,
            'document'       => $payer['document'],
            'name'           => $payer['name'],
            'identification' => $payer['identification'] ?? null,
            'expire'         => $payer['expire'] ?? 3600,
        ];

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/create-payment", $payload);

        if (!$response->successful() || !$response->json('success')) {
            throw new Exception("Erro ao criar pagamento na GetPay: " . $response->body());
        }

        return $response->json('data');
    }

    /**
     * A API legacy não possui consulta
     */
    public function getTransactionStatus(string $transactionId)
    {
        throw new Exception("A GetPay Legacy API não possui endpoint de consulta.");
    }

    /**
     * Saque
     */
    public function withdraw(float $amount, array $recipient)
    {
        $token = $this->getToken();

        $response = Http::withToken($token)->post("{$this->baseUrl}/withdrawals", [
            'amount'   => $amount,
            'document' => $recipient['document'],
            'name'     => $recipient['name'],
        ]);

        if (!$response->successful() || !$response->json('success')) {
            throw new Exception("Erro ao solicitar saque na GetPay: " . $response->body());
        }

        return $response->json();
    }

    public function processWebhook(array $payload)
    {
        return [
            'processed' => true,
            'payload'   => $payload,
        ];
    }
}
