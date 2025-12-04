<?php

namespace App\Services\Pluggou;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PluggouCashoutService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected ?string $organizationId;

    public function __construct()
    {
        $this->baseUrl       = rtrim(config('services.pluggou.base_url', 'https://api.pluggoutech.com/api'), '/');
        $this->apiKey        = config('services.pluggou.api_key');
        $this->organizationId = config('services.pluggou.organization_id');
    }

    /**
     * Cria um saque (cashout PIX) via API da Pluggou.
     *
     * @param  array{amount:int,key_type:string,key_value:string}  $payload
     * @return array
     */
    public function createCashout(array $payload): array
    {
        try {
            Log::info('[PLUGGOU CASHOUT] Iniciando requisição', [
                'payload' => $payload,
            ]);

            // 🔎 Validação básica
            if (!isset($payload['amount'], $payload['key_type'], $payload['key_value'])) {
                throw new \InvalidArgumentException('Payload inválido: amount, key_type e key_value são obrigatórios.');
            }

            // ✅ Requisição real
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-API-Key'      => $this->apiKey,
                    'Accept'         => 'application/json',
                    'Content-Type'   => 'application/json',
                ])
                ->post("{$this->baseUrl}/payments/transactions", [
                    'organizationId' => $this->organizationId,
                    'amount'         => $payload['amount'], // valor em centavos
                    'keyType'        => $payload['key_type'],
                    'keyValue'       => $payload['key_value'],
                    'description'    => $payload['description'] ?? 'Saque automático via API',
                ]);

            $data = $response->json() ?? [];

            Log::info('[PLUGGOU CASHOUT] Resposta da API', [
                'http_status' => $response->status(),
                'response'    => $data,
            ]);

            // 🔴 Erro HTTP (timeout, validação, etc)
            if (!$response->successful()) {
                return [
                    'success'  => false,
                    'message'  => $data['message'] ?? 'Erro de comunicação com a API Pluggou',
                    'response' => $data,
                ];
            }

            // 🟢 Normalização da resposta
            return [
                'success' => (bool) ($data['success'] ?? true),
                'message' => $data['message'] ?? 'Cashout processado com sucesso',
                'data'    => $data['data'] ?? $data,
            ];

        } catch (\Throwable $e) {
            Log::error('[PLUGGOU CASHOUT] Exceção capturada', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success'   => false,
                'message'   => 'Erro interno ao comunicar com a API da Pluggou.',
                'exception' => $e->getMessage(),
            ];
        }
    }
}
