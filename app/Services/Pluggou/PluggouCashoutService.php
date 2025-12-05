<?php

namespace App\Services\Pluggou;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PluggouCashoutService
{
    protected string $baseUrl;
    protected string $publicKey;
    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl   = rtrim(config('services.pluggou.api_url', 'https://api.pluggoutech.com/api'), '/');
        $this->publicKey = (string) config('services.pluggou.public_key', '');
        $this->secretKey = (string) config('services.pluggou.secret_key', '');

        if (empty($this->publicKey) || empty($this->secretKey)) {
            Log::error('[PLUGGOU CASHOUT] ❌ Chaves ausentes.');
            throw new \RuntimeException('Chaves de API da Pluggou ausentes no .env');
        }
    }

    /**
     * Cria um saque (cashout PIX) via API da Pluggou.
     *
     * Retorno SEMPRE padronizado:
     *
     * [
     *   'success' => bool,
     *   'message' => string,
     *   'provider_id' => string|null,
     *   'provider_status' => string|null,
     *   'data' => array|null
     * ]
     */
    public function createCashout(array $payload): array
    {
        try {
            Log::info('[PLUGGOU CASHOUT] 🚀 Enviando requisição', ['payload' => $payload]);

            // 🔎 Validação básica
            if (!isset($payload['amount'], $payload['key_type'], $payload['key_value'])) {
                return [
                    'success' => false,
                    'message' => 'Payload inválido: amount, key_type e key_value são obrigatórios.',
                    'provider_id' => null,
                    'provider_status' => null,
                    'data' => null,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | 1) Chamada HTTP
            |--------------------------------------------------------------------------
            */
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Public-Key' => $this->publicKey,
                    'X-Secret-Key' => $this->secretKey,
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/withdrawals", [
                    'amount'      => $payload['amount'], 
                    'key_type'    => strtolower($payload['key_type']),
                    'key_value'   => $payload['key_value'],
                    'description' => $payload['description'] ?? 'Saque automático via API',
                ]);

            $json = $response->json() ?? [];

            Log::info('[PLUGGOU CASHOUT] 📩 Resposta', [
                'http_status' => $response->status(),
                'response'    => $json,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 2) HTTP FAILURE
            |--------------------------------------------------------------------------
            */
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $json['message'] ?? 'Erro HTTP ao comunicar com a Pluggou',
                    'provider_id' => null,
                    'provider_status' => null,
                    'data' => $json,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Extrair provider_id e status corretamente
            |--------------------------------------------------------------------------
            */
            $providerId = data_get($json, 'data.id') 
                       ?? data_get($json, 'id');

            $providerStatus = strtoupper(data_get($json, 'data.status', 
                                data_get($json, 'status', 'PROCESSING')));

            /*
            |--------------------------------------------------------------------------
            | 4) Normalização do retorno
            |--------------------------------------------------------------------------
            */
            return [
                'success'         => (bool) ($json['success'] ?? true),
                'message'         => $json['message'] ?? 'Requisição aceita pela Pluggou',
                'provider_id'     => $providerId,
                'provider_status' => $providerStatus,
                'data'            => $json['data'] ?? $json,
            ];

        } catch (\Throwable $e) {

            Log::error('[PLUGGOU CASHOUT] 💥 EXCEPTION', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success'         => false,
                'message'         => 'Erro interno ao comunicar com a API da Pluggou.',
                'provider_id'     => null,
                'provider_status' => null,
                'data'            => null,
            ];
        }
    }
}
