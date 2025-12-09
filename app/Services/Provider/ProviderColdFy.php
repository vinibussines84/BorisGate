<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProviderColdFy
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
            Log::critical('⚠️ ColdFy: Credenciais ausentes', [
                'secret_key' => $this->secretKey,
                'company_id' => $this->companyId,
            ]);

            throw new Exception("ColdFy: credenciais ausentes. Verifique o arquivo .env ou config/services.php");
        }

        $this->authorization = base64_encode("{$this->secretKey}:{$this->companyId}");
    }

    protected function request(string $method, string $endpoint, array $data = [])
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Basic {$this->authorization}",
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])
            ->timeout(config('services.coldfy.timeout', 15))
            ->$method("{$this->baseUrl}{$endpoint}", $data);

            if ($response->failed()) {
                Log::error("❌ COLDFY_API_ERROR", [
                    'endpoint' => $endpoint,
                    'data' => $data,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new Exception("Erro ao comunicar com a API ColdFy (HTTP {$response->status()})");
            }

            return $response->json();

        } catch (\Throwable $e) {
            Log::error("🚨 COLDFY_HTTP_EXCEPTION", [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);

            throw new Exception("Falha ao comunicar com o provedor ColdFy: {$e->getMessage()}");
        }
    }

    /**
     * Criar pagamento PIX
     */
    public function createPix(float $amount, array $payer)
    {
        $data = [
            "customer" => [
                "name"    => $payer["name"] ?? 'Cliente',
                "email"   => $payer["email"] ?? 'cliente@exemplo.com',
                "phone"   => preg_replace('/\D/', '', $payer["phone"] ?? ''),
                "document" => [
                    "number" => preg_replace('/\D/', '', $payer["document"] ?? ''),
                    "type"   => (strlen(preg_replace('/\D/', '', $payer["document"] ?? '')) === 11 ? "CPF" : "CNPJ")
                ],
            ],

            "paymentMethod" => "PIX",

            "items" => [
                [
                    "title"      => "Pix",
                    "unitPrice"  => intval($amount * 100),
                    "quantity"   => 1,
                ]
            ],

            "amount" => intval($amount * 100),

            // rota definida em routes/api.php → name('webhooks.coldfy')
            "postbackUrl" => route("webhooks.coldfy"),
        ];

        Log::info("💸 Enviando requisição PIX para ColdFy", $data);

        return $this->request("post", "/transactions", $data);
    }

    /**
     * Consultar status da transação
     */
    public function getTransactionStatus(string $transactionId)
    {
        Log::info("🔍 Consultando status da transação ColdFy", [
            'transaction_id' => $transactionId,
        ]);

        return $this->request("get", "/transactions/{$transactionId}");
    }

    /**
     * ColdFy NÃO possui endpoint de saque
     */
    public function withdraw(float $amount, array $recipient)
    {
        throw new Exception("ColdFy não possui endpoint de saque.");
    }

    /**
     * Processamento do Webhook
     */
    public function processWebhook(array $payload)
    {
        Log::info("📬 COLDFY_WEBHOOK_RECEIVED", $payload);

        return [
            "success" => true,
            "received" => $payload,
        ];
    }
}
