<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProviderColdFyOut
{
    protected string $baseUrl = "https://api.coldfypay.com/functions/v1";
    protected string $secretKey;
    protected string $companyId;
    protected string $authorization;

    public function __construct()
    {
        $this->secretKey = (string) config('services.coldfy.secret_key');
        $this->companyId = (string) config('services.coldfy.company_id');

        if (empty($this->secretKey) || empty($this->companyId)) {
            Log::critical('⚠️ ColdFyOut: Credenciais ausentes');
            throw new Exception("ColdFyOut: credenciais ausentes.");
        }

        $this->authorization = base64_encode("{$this->secretKey}:{$this->companyId}");
    }

    /**
     * Criar saque PIX no provider
     */
    public function createCashout(array $payload): array
    {
        $endpoint = "/withdrawals/cashout";

        $data = [
            'isPix'           => true,
            'pixkeytype'      => strtolower($payload['pix_key_type']),
            'pixkey'          => $payload['pix_key'],
            'requestedamount' => intval($payload['amount'] * 100),
            'description'     => $payload['description'],
            'postbackUrl'     => route('webhooks.coldfy.out'),
        ];

        /**
         * IDEMPOTÊNCIA REAL
         * Assim, qualquer retry do job vai gerar SEMPRE a mesma resposta ColdFy.
         */
        $idempotencyKey = "withdraw_{$payload['external_id']}";

        Log::info("💸 Enviando saque ColdFyOut", [
            'payload' => $data,
            'idempotency' => $idempotencyKey,
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization'   => "Basic {$this->authorization}",
                'Content-Type'    => 'application/json',
                'Accept'          => 'application/json',
                'Idempotency-Key' => $idempotencyKey,
            ])
                ->timeout(20)
                ->post($this->baseUrl . $endpoint, $data);

            // ⚠️ ColdFy usa HTTP 429 quando saque duplicado em menos de 10s
            if ($response->status() === 429) {
                Log::warning("⏳ ColdFy rate-limit — retry permitido");
                throw new Exception("RATE_LIMIT");
            }

            if ($response->failed()) {
                Log::error("❌ ERRO AO CRIAR SAQUE NO PROVIDER", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new Exception("Erro ao criar saque ColdFyOut (HTTP {$response->status()})");
            }

            return $response->json();

        } catch (\Throwable $e) {

            Log::error("🚨 EXCEÇÃO COLDYOUT", [
                'error' => $e->getMessage(),
            ]);

            throw new Exception($e->getMessage());
        }
    }
}
