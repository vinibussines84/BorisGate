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
            Log::critical('⚠️ ColdFyOut: Credenciais ausentes', [
                'secret_key' => $this->secretKey,
                'company_id' => $this->companyId,
            ]);

            throw new Exception("ColdFyOut: credenciais ausentes. Verifique o arquivo .env ou config/services.php");
        }

        $this->authorization = base64_encode("{$this->secretKey}:{$this->companyId}");
    }

    /**
     * Criar saque (Cashout PIX)
     */
    public function createCashout(array $payload): array
    {
        $data = [
            "isPix"           => (bool) data_get($payload, "isPix", true),
            "pixkeyid"        => data_get($payload, "pixkeyid"),
            "pixkeytype"      => data_get($payload, "pixkeytype"),
            "pixkey"          => data_get($payload, "pixkey"),
            "requestedamount" => (int) data_get($payload, "requestedamount"), // em centavos
            "description"     => data_get($payload, "description", "Saque via API"),
            "postbackUrl"     => data_get($payload, "postbackUrl", route("webhooks.coldfy")),
        ];

        $idempotency = data_get($payload, "idempotency_key") ?? uniqid("cashout_", true);

        Log::info("💸 Enviando requisição CASHOUT (ColdFyOut)", [
            'endpoint'        => '/withdrawals/cashout',
            'data'            => $data,
            'idempotency_key' => $idempotency,
        ]);

        try {
            $response = Http::withHeaders([
                    'Authorization'   => "Basic {$this->authorization}",
                    'Accept'          => 'application/json',
                    'Content-Type'    => 'application/json',
                    'Idempotency-Key' => $idempotency,
                ])
                ->timeout(config('services.coldfy.timeout', 20))
                ->post("{$this->baseUrl}/withdrawals/cashout", $data);

            if ($response->failed()) {
                Log::error("❌ COLDFYOUT_API_ERROR", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                throw new Exception("Erro ao criar saque na ColdFy (HTTP {$response->status()})");
            }

            $json = $response->json();

            Log::info("✅ CASHOUT criado com sucesso (ColdFyOut)", [
                'response' => $json,
            ]);

            return $json;

        } catch (\Throwable $e) {
            Log::error("🚨 ERRO CASHOUT_COLDFYOUT", [
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Falha ao criar saque na ColdFy: {$e->getMessage()}");
        }
    }

    /**
     * Consultar saque (opcional)
     */
    public function getCashoutStatus(string $withdrawalId): array
    {
        Log::info("🔍 Consultando saque ColdFyOut", [
            'withdrawal_id' => $withdrawalId,
        ]);

        try {
            $response = Http::withHeaders([
                    'Authorization' => "Basic {$this->authorization}",
                    'Accept'        => 'application/json',
                ])
                ->timeout(15)
                ->get("{$this->baseUrl}/withdrawals/{$withdrawalId}");

            if ($response->failed()) {
                Log::error("❌ ERRO AO CONSULTAR SAQUE (ColdFyOut)", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                throw new Exception("Erro ao consultar saque na ColdFy (HTTP {$response->status()})");
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error("🚨 ERRO HTTP GET_CASHOUT_COLDFYOUT", [
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Falha ao consultar saque na ColdFy: {$e->getMessage()}");
        }
    }
}
