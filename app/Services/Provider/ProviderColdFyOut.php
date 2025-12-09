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
        $this->authorization = config('services.coldfy.auth'); // Basic d3d3dzp3d3d3dw==
    }

    /**
     * Criar saque (cashout PIX)
     */
    public function createCashout(array $payload): array
    {
        $endpoint = "{$this->baseUrl}/withdrawals/cashout";

        // 🔹 Gera um pixkeyid único por saque
        $pixKeyId = 'pix_' . Str::uuid()->toString();

        // 🔹 Monta o corpo no formato exigido pela ColdFy
        $data = [
            'isPix'           => true,
            'pixkeyid'        => $pixKeyId,
            'pixkeytype'      => strtolower($payload['pixKeyType'] ?? ''),
            'pixkey'          => $payload['pixKey'] ?? '',
            'requestedamount' => intval(($payload['amount'] ?? 0) * 100),
            'description'     => $payload['description'] ?? 'Saque diário do parceiro',
            'postbackUrl'     => route('webhooks.coldfy'),
        ];

        // 🔹 Gera chave de idempotência única
        $idempotencyKey = 'cashout_' . Str::random(10);

        Log::info('💸 Enviando requisição CASHOUT (ColdFyOut)', [
            'endpoint'         => '/withdrawals/cashout',
            'data'             => $data,
            'idempotency_key'  => $idempotencyKey,
        ]);

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
