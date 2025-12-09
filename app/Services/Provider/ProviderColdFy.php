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
        $this->secretKey = config('services.coldfy.secret_key');
        $this->companyId = config('services.coldfy.company_id');

        if (!$this->secretKey || !$this->companyId) {
            throw new Exception("ColdFy: Credenciais ausentes no .env");
        }

        $this->authorization = base64_encode("{$this->secretKey}:{$this->companyId}");
    }

    protected function request($method, $endpoint, $data = [])
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Basic {$this->authorization}",
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json'
            ])->$method("{$this->baseUrl}{$endpoint}", $data);

            if ($response->failed()) {
                Log::error("COLDFY_API_ERROR", [
                    'endpoint' => $endpoint,
                    'data' => $data,
                    'response' => $response->body(),
                ]);

                throw new Exception("Erro na API ColdFy.");
            }

            return $response->json();

        } catch (\Throwable $e) {
            Log::error("COLDFY_HTTP_EXCEPTION", [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);

            throw new Exception("Falha ao comunicar com ColdFy.");
        }
    }

    /**
     * Criar pagamento PIX
     */
    public function createPix(float $amount, array $payer)
    {
        $data = [
            "customer" => [
                "name"    => $payer["name"],
                "email"   => $payer["email"],
                "phone"   => preg_replace('/\D/', '', $payer["phone"]),
                "document" => [
                    "number" => preg_replace('/\D/', '', $payer["document"]),
                    "type"   => strlen($payer["document"]) === 11 ? "CPF" : "CNPJ"
                ]
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

            "postbackUrl" => route("coldfy.webhook"),
        ];

        return $this->request("post", "/transactions", $data);
    }

    /**
     * Consultar status da transação
     */
    public function getTransactionStatus(string $transactionId)
    {
        return $this->request("get", "/transactions/{$transactionId}");
    }

    /**
     * ColdFy NÃO possui saque → implementar stub
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
        Log::info("COLDFY_WEBHOOK_RECEIVED", $payload);

        // Exemplo: você trata status aqui
        // $payload['status'] → paid, pending, canceled, failed…

        return [
            "success" => true,
            "received" => $payload,
        ];
    }
}
