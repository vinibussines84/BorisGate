<?php

namespace App\Services\Pluggou;

use Illuminate\Support\Facades\Http;

class PluggouService
{
    protected string $baseUrl;
    protected string $public;
    protected string $secret;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.pluggou.base_url', 'https://api.pluggoutech.com/api'), '/');
        $this->public  = config('services.pluggou.public_key');
        $this->secret  = config('services.pluggou.secret_key');
        $this->timeout = (int) config('services.pluggou.timeout', 15);
    }

    /**
     * Cria transação PIX na Pluggou
     */
    public function createTransaction(array $payload): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    "X-Public-Key" => $this->public,
                    "X-Secret-Key" => $this->secret,
                    "Accept"       => "application/json",
                    "Content-Type" => "application/json",
                ])
                ->post($this->baseUrl . "/transactions", $payload);

            $json = null;

            try {
                $json = $response->json(); // pode ser null se a Pluggou retornar HTML
            } catch (\Throwable $e) {
                //
            }

            return [
                "status" => $response->status(),
                "body"   => $json ?: [
                    "success" => false,
                    "message" => "Resposta inválida da Pluggou",
                    "raw"     => $response->body(),
                ],
                "raw"    => $response->body(),
            ];

        } catch (\Throwable $e) {

            return [
                "status" => 500,
                "body" => [
                    "success" => false,
                    "message" => "Erro interno ao chamar Pluggou: " . $e->getMessage(),
                ],
            ];
        }
    }
}
