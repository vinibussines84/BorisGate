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
            Log::error('[PLUGGOU CASHOUT] ❌ Chaves ausentes. Configure PLUGGOU_PUBLIC_KEY e PLUGGOU_SECRET_KEY no .env.');
            throw new \RuntimeException('Chaves de API da Pluggou ausentes no .env');
        }
    }

    /**
     * Cria um saque (cashout PIX) via API da Pluggou.
     *
     * @param  array{amount:int,key_type:string,key_value:string,description?:string}  $payload
     * @return array
     */
    public function createCashout(array $payload): array
    {
        try {
            Log::info('[PLUGGOU CASHOUT] 🚀 Iniciando requisição', ['payload' => $payload]);

            // 🔎 Validação básica
            if (!isset($payload['amount'], $payload['key_type'], $payload['key_value'])) {
                throw new \InvalidArgumentException('Payload inválido: amount, key_type e key_value são obrigatórios.');
            }

            /*
            |--------------------------------------------------------------------------
            | 1) Monta requisição HTTP para a Pluggou
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
                    'amount'      => $payload['amount'], // valor em centavos
                    'key_type'    => strtolower($payload['key_type']),
                    'key_value'   => $payload['key_value'],
                    'description' => $payload['description'] ?? 'Saque automático via API',
                ]);

            $data = $response->json() ?? [];

            Log::info('[PLUGGOU CASHOUT] 📩 Resposta recebida', [
                'http_status' => $response->status(),
                'response'    => $data,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 2) Validação do retorno HTTP
            |--------------------------------------------------------------------------
            */
            if (!$response->successful()) {
                return [
                    'success'  => false,
                    'message'  => $data['message'] ?? 'Erro de comunicação com a API Pluggou',
                    'response' => $data,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Normalização do retorno
            |--------------------------------------------------------------------------
            */
            return [
                'success' => (bool) ($data['success'] ?? true),
                'message' => $data['message'] ?? 'Saque processado com sucesso',
                'data'    => $data['data'] ?? $data,
            ];
        } catch (\Throwable $e) {
            Log::error('[PLUGGOU CASHOUT] 💥 Exceção capturada', [
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
