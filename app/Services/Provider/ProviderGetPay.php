<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class ProviderGetPay
{
    protected string $baseUrl = 'https://hub.getpay.one/api';

    /**
     * Obtém o token JWT, com cache automático.
     */
    protected function getToken(): string
    {
        if (Cache::has('getpay_jwt')) {
            return Cache::get('getpay_jwt');
        }

        $response = Http::post("{$this->baseUrl}/login", [
            'email' => config('services.getpay.email'),
            'password' => config('services.getpay.password'),
        ]);

        if (!$response->successful() || !$response->json('success')) {
            throw new Exception("Falha ao autenticar na GetPay: " . $response->body());
        }

        $token = $response->json('token');

        Cache::put('getpay_jwt', $token, now()->addMinutes(55));

        return $token;
    }

    /**
     * Criar PIX (equivalente ao create-payment)
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
     * A API legacy não possui este endpoint, deixo implementado para compatibilidade.
     */
    public function getTransactionStatus(string $transactionId)
    {
        throw new Exception("A GetPay Legacy API não possui endpoint de consulta.");
    }

    /**
     * Saque — API Legacy usa /api/withdrawals
     */
    public function withdraw(float $amount, array $recipient)
    {
        $token = $this->getToken();

        $response = Http::withToken($token)->post("{$this->baseUrl}/withdrawals", [
            'amount' => $amount,
            'document' => $recipient['document'],
            'name' => $recipient['name'],
        ]);

        if (!$response->successful() || !$response->json('success')) {
            throw new Exception("Erro ao solicitar saque na GetPay: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Processar Webhook
     */
    public function processWebhook(array $payload)
    {
        // personalizar conforme sua regra
        return [
            'processed' => true,
            'payload' => $payload,
        ];
    }
}
