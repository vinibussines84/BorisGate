<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ProviderCoffePay
{
    protected $baseUrl;
    protected $clientId;
    protected $clientSecret;

    public function __construct()
    {
        $this->baseUrl      = config('services.coffepay.url');
        $this->clientId     = config('services.coffepay.client_id');
        $this->clientSecret = config('services.coffepay.client_secret');
    }

    /**
     * TOKEN - obtém token da API com cache automático
     */
    protected function token()
    {
        return Cache::remember('coffepay_token', 3500, function () {
            $response = Http::asJson()->post("{$this->baseUrl}/auth/login", [
                "clientId"     => $this->clientId,
                "clientSecret" => $this->clientSecret
            ]);

            return $response->json("data.token");
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

        $data = $response->json('data');

        return [
            "provider" => "coffepay",
            "transaction_id" => $data['transaction'],
            "amount"         => $data['amount'],
            "payer_name"     => $data['payer_name'],
            "payer_document" => $data['payer_document'],
            "qrcode"         => $data['qrcode'],
            "qrcode_image"   => $data['qrcode_image'],
            "external_id"    => $data['external_id'] ?? null,
            "status"         => "pending", // seu sistema usa PENDENTE ao criar
        ];
    }

    /**
     * Consulta status da transação
     * (simulado — ajuste quando o endpoint existir)
     */
    public function getTransactionStatus(string $transactionId)
    {
        // se houver endpoint real: substitua aqui
        return [
            "transaction_id" => $transactionId,
            "status" => "pending"
        ];
    }

    /**
     * Cash-out (saque)
     * (aguardando documentação — estrutura pronta)
     */
    public function withdraw(float $amount, array $recipient)
    {
        // Ajustar quando houver endpoint oficial
        return [
            "status" => "processing",
            "withdraw_id" => "temp-id",
        ];
    }

    /**
     * Processamento do webhook
     * Mapeia o status COFFE PAY → seus status internos
     */
    public function processWebhook(array $payload)
    {
        $statusProvider = strtolower($payload['status'] ?? '');

        $statusMapped = match ($statusProvider) {
            "success"     => "pago",
            "processing"  => "pendente",
            "failed"      => "falha",
            default       => "pendente",
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
