<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            Log::critical('⚠️ ColdFyOut: Credenciais ausentes', [
                'secret_key' => $this->secretKey,
                'company_id' => $this->companyId,
            ]);

            throw new Exception("ColdFyOut: credenciais ausentes. Verifique .env ou config/services.php");
        }

        // Basic Auth correto para ColdFy
        $this->authorization = base64_encode("{$this->secretKey}:{$this->companyId}");
    }

    /**
     * Criar saque PIX NO provider
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
            'postbackUrl'     => route('webhooks.coldfy'),
        ];

        $idempotencyKey = 'cashout_' . Str::random(12);

        Log::info("💸 Enviando saque ColdFyOut", [
            'endpoint' => $endpoint,
            'payload'  => $data,
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization'   => "Basic {$this->authorization}",
                'Content-Type'    => 'application/json',
                'Accept'          => 'application/json',
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->timeout(config('services.coldfy.timeout', 15))
            ->post($this->baseUrl . $endpoint, $data);

            if ($response->failed()) {
                Log::error("❌ COLDFYOUT_API_ERROR", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new Exception("Erro ao criar saque ColdFyOut (HTTP {$response->status()})");
            }

            return $response->json();

        } catch (\Throwable $e) {
            Log::error("🚨 COLDFYOUT_HTTP_EXCEPTION", [
                'error'    => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);

            throw new Exception("Falha ao comunicar com ColdFyOut: {$e->getMessage()}");
        }
    }
}
