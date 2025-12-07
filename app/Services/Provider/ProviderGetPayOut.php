<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProviderGetPayOut
{
    protected string $baseUrl = 'https://hub.getpay.one/api';

    public function __construct(
        private readonly ProviderGetPay $authProvider // usa o mesmo JWT
    ) {}

    /**
     * Criar saque via GETPAY (API Legacy JWT)
     *
     * Espera payload:
     * [
     *   'externalId'     => string,
     *   'pixKey'         => string,
     *   'pixKeyType'     => CPF|CNPJ|EMAIL|PHONE|EVP,
     *   'documentNumber' => CPF/CNPJ do destinatário,
     *   'name'           => Nome completo,
     *   'amount'         => float
     * ]
     */
    public function createWithdrawal(array $payload): array
    {
        $token = $this->authProvider->getToken();

        Log::info('[ProviderGetPayOut] 🚀 Enviando withdrawal para GetPay', [
            'payload' => $payload
        ]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ])->post("{$this->baseUrl}/withdrawals", [
            'externalId'     => $payload['externalId'],
            'pixKey'         => $payload['pixKey'],
            'pixKeyType'     => strtoupper($payload['pixKeyType']),
            'documentNumber' => $payload['documentNumber'],
            'name'           => $payload['name'],
            'amount'         => (float) $payload['amount'], // ⚠ Não é centavos!
        ]);

        $json = $response->json();

        Log::info('[ProviderGetPayOut] 📩 Resposta GetPay', [
            'payload'  => $payload,
            'response' => $json
        ]);

        if (!$response->successful() || !$json['success']) {
            Log::error('[ProviderGetPayOut] ❌ Falha ao criar withdraw', [
                'payload'   => $payload,
                'response'  => $json,
            ]);

            throw new Exception("[GetPayOut] Erro ao solicitar saque: " . $response->body());
        }

        return $json;
    }
}
