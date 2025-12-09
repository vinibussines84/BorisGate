<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProviderColdFyOut
{
    protected string $baseUrl;
    protected string $authorization;

    public function __construct()
    {
        $this->baseUrl = config('services.coldfy.base_url', 'https://api.coldfypay.com/functions/v1');
        $this->authorization = config('services.coldfy.auth');
    }

    /**
     * Criar saque (cashout PIX)
     */
    public function createCashout(array $payload): array
    {
        $endpoint = "{$this->baseUrl}/withdrawals/cashout";

        /*
        |--------------------------------------------------------------------------
        | Construção final do payload compatível com a API ColdFy
        |--------------------------------------------------------------------------
        */

        $data = [
            'isPix'           => true,
            'pixkeytype'      => strtolower($payload['pixKeyType']), // email, cpf, etc.
            'pixkey'          => $payload['pixKey'],                 // chave limpa
            'requestedamount' => intval($payload['amount'] * 100),   // converter para centavos
            'description'     => $payload['description'],
            'postbackUrl'     => route('webhooks.coldfy'),           // tem que ser https em produção
        ];

        /*
        |--------------------------------------------------------------------------
        | Idempotência
        |--------------------------------------------------------------------------
        */
        $idempotencyKey = 'cashout_' . Str::random(12);

        Log::info('💸 Enviando requisição CASHOUT (ColdFyOut)', [
            'endpoint' => $endpoint,
            'payload'  => $data,
            'idempotency_key' => $idempotencyKey,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Requisição HTTP para a ColdFy
        |--------------------------------------------------------------------------
        */
        try {
            $response = Http::withHeaders([
                'Authorization'   => 'Basic ' . $this->authorization,
                'Content-Type'    => 'application/json',
                'Accept'          => 'application/json',
                'Idempotency-Key' => $idempotencyKey,
            ])->post($endpoint, $data);

            if ($response->failed()) {
                Log::error('❌ COLDFYOUT_API_ERROR', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \Exception("Erro ao criar saque na ColdFy (HTTP {$response->status()})");
            }

            $json = $response->json();

            Log::info('✅ CASHOUT criado com sucesso (ColdFyOut)', [
                'response' => $json,
            ]);

            return $json;

        } catch (\Throwable $e) {
            Log::error('🚨 ERRO CASHOUT_COLDFYOUT', [
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Falha ao criar saque na ColdFy: " . $e->getMessage());
        }
    }
}
