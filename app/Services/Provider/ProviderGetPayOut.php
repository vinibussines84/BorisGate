<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProviderGetPayOut
{
    protected string $baseUrl = 'https://hub.getpay.one/api';

    public function __construct(
        private readonly ProviderGetPay $authProvider // obtém JWT correto
    ) {}

    /**
     * Criar saque via GETPAY (API Legacy JWT)
     *
     * Payload esperado:
     * [
     *   'externalId'     => string,
     *   'pixKey'         => string,
     *   'pixKeyType'     => CPF|CNPJ|EMAIL|PHONE|EVP,
     *   'documentNumber' => string,
     *   'name'           => string,
     *   'amount'         => float,
     * ]
     */
    public function createWithdrawal(array $payload): array
    {
        // 🔐 Garantir que token é obtido corretamente
        $token = $this->authProvider->getToken();

        // 🔍 Log detalhado para debug
        Log::info('[ProviderGetPayOut] 🚀 Enviando withdrawal para GetPay', [
            'payload' => $payload
        ]);

        // 🔥 Chamada HTTP oficial da GETPAY
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ])->post("{$this->baseUrl}/withdrawals", [
            'externalId'     => $payload['externalId']     ?? null,
            'pixKey'         => $payload['pixKey']         ?? null,
            'pixKeyType'     => strtoupper($payload['pixKeyType'] ?? ''),
            'documentNumber' => $payload['documentNumber'] ?? null,
            'name'           => $payload['name']           ?? null,
            'amount'         => (float) ($payload['amount'] ?? 0),
        ]);

        $json = $response->json();

        // Log sempre, mesmo em falha
        Log::info('[ProviderGetPayOut] 📩 Resposta GetPay', [
            'payload'  => $payload,
            'response' => $json
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validação da resposta
        |--------------------------------------------------------------------------
        */
        if (!$response->successful()) {
            Log::error('[ProviderGetPayOut] ❌ HTTP ERROR ao criar withdraw', [
                'status'   => $response->status(),
                'payload'  => $payload,
                'response' => $json,
            ]);

            throw new Exception("[GetPayOut] Falha HTTP: " . $response->body());
        }

        if (!isset($json['success']) || $json['success'] !== true) {
            Log::error('[ProviderGetPayOut] ❌ GetPay retornou erro lógico', [
                'payload'  => $payload,
                'response' => $json,
            ]);

            $reason = $json['message'] ?? 'Erro desconhecido do provider';

            throw new Exception("[GetPayOut] Erro ao solicitar saque: {$reason}");
        }

        return $json;
    }
}
