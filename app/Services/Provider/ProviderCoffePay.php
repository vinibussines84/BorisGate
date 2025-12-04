<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class ProviderCoffePay
{
    protected $baseUrl;
    protected $clientId;
    protected $clientSecret;

    public function __construct()
    {
        $this->baseUrl      = rtrim(config('services.coffepay.url'), '/');
        $this->clientId     = config('services.coffepay.client_id');
        $this->clientSecret = config('services.coffepay.client_secret');
    }

    /**
     * TOKEN - obtém token da CoffePay
     */
    protected function token()
    {
        return Cache::remember('coffepay_token', 3000, function () {

            $response = Http::asJson()->post(
                "{$this->baseUrl}/login",
                [
                    "clientId"     => $this->clientId,
                    "clientSecret" => $this->clientSecret
                ]
            );

            $json = $response->json();

            Log::info("COFFE_PAY_LOGIN_RESPONSE", $json);

            // Tenta extrair o token
            $token = data_get($json, 'data.token');

            if (!$token) {

                Log::error("COFFE_PAY_LOGIN_FAILED", [
                    'response' => $json
                ]);

                throw new Exception("CoffePay não retornou token válido.");
            }

            return $token;
        });
    }

    /**
     * Criar PIX (cash-in)
     */
    public function createPix(float $amount, array $payer)
    {
        $response = Http::withToken($this->token())
            ->asJson()
            ->post("{$this->baseUrl}/transaction", [
                "amount"       => $amount,
                "payment_type" => "pix",
                "payer" => [
                    "name"     => $payer['name'],
                    "document" => $payer['document'],
                    "email"    => $payer['email'] ?? null
                ]
            ]);

        $json = $response->json();
        Log::info("COFFE_PAY_CREATE_PIX_RESPONSE", $json);

        $data = data_get($json, 'data');

        if (!$data) {
            Log::error("COFFE_PAY_CREATE_PIX_FAILED", ['response' => $json]);
            throw new Exception("CoffePay retornou resposta inválida ao criar PIX.");
        }

        return [
            "provider"         => "coffepay",
            "transaction_id"   => $data['transaction'] ?? null,
            "amount"           => $data['amount'] ?? null,
            "payer_name"       => $data['payer_name'] ?? null,
            "payer_document"   => $data['payer_document'] ?? null,
            "qrcode"           => $data['qrcode'] ?? null,
            "qrcode_image"     => $data['qrcode_image'] ?? null,
            "external_id"      => $data['external_id'] ?? null,
            "status"           => "pending",
        ];
    }

    /**
     * Consulta status da transação
     * (ajuste futuro quando existir endpoint oficial)
     */
    public function getTransactionStatus(string $transactionId)
    {
        return [
            "transaction_id" => $transactionId,
            "status" => "pending"
        ];
    }

    /**
     * Cash-out (saque)
     */
    public function withdraw(float $amount, array $recipient)
    {
        return [
            "status"      => "processing",
            "withdraw_id" => "temp-id",
        ];
    }

    /**
     * Processamento Webhook
     */
    public function processWebhook(array $payload)
    {
        $statusProvider = strtolower($payload['status'] ?? '');

        $statusMapped = match ($statusProvider) {
            "success"    => "pago",
            "processing" => "pendente",
            "failed"     => "falha",
            default      => "pendente",
        };

        return [
            "provider"         => "coffepay",
            "transaction_id"   => $payload['transaction'] ?? null,
            "status_original"  => $statusProvider,
            "status_mapped"    => $statusMapped,
            "amount"           => $payload['amount'] ?? null,
            "external_id"      => $payload['external_id'] ?? null,
        ];
    }
}
