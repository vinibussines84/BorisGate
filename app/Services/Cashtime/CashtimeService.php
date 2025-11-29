<?php

namespace App\Services\Cashtime;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CashtimeService
{
    protected string $baseUrl;
    protected string $key;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.cashtime.base_url'), '/');
        $this->key     = (string) config('services.cashtime.key');
        $this->timeout = (int) config('services.cashtime.timeout', 15);

        if (empty($this->baseUrl)) {
            Log::error("CashtimeService: CASHTIME_BASE_URL está vazio.");
        }

        if (empty($this->key)) {
            Log::error("CashtimeService: CASHTIME_KEY está vazio.");
        }
    }

    /**
     * Cria cobrança Pix.
     */
    public function createCob(array $payload): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-authorization-key' => $this->key,
                    'Accept'              => 'application/json',
                ])
                ->post($this->baseUrl . '/v1/cob', $payload);

            // ---------------------------------------------------
            // TRATAR STATUS HTTP
            // ---------------------------------------------------
            if ($response->failed()) {

                Log::error("Cashtime Service - HTTP ERROR", [
                    'status'   => $response->status(),
                    'payload'  => $payload,
                    'response' => $response->json(),
                ]);

                return [
                    'success'  => false,
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ];
            }

            // ---------------------------------------------------
            // TENTAR PEGAR JSON DE FORMA SEGURA
            // ---------------------------------------------------
            $json = $response->json();

            if (!is_array($json)) {
                Log::error("Cashtime Service - Resposta não é JSON válido", [
                    'payload'  => $payload,
                    'body'     => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error'   => "Resposta inválida da Cashtime",
                ];
            }

            return $json;

        } catch (\Throwable $e) {

            Log::error("Cashtime Service - EXCEPTION", [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
