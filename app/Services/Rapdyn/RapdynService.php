<?php

namespace App\Services\Rapdyn;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RapdynService
{
    private string $baseUrl;
    private string $token;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            config('services.rapdyn.base_url') ?? env('RAPDYN_BASE_URL', ''),
            '/'
        );

        $this->token = config('services.rapdyn.token') ?? env('RAPDYN_TOKEN', '');
        $this->timeout = (int) (config('services.rapdyn.timeout', 15));

        if (empty($this->baseUrl) || empty($this->token)) {
            throw new RuntimeException('❌ Variáveis RAPDYN_BASE_URL ou RAPDYN_TOKEN ausentes no .env');
        }
    }

    /**
     * Cria cobrança PIX na Rapdyn
     */
    public function createCob(array $payload): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type'  => 'application/json',
                ])
                ->post($this->baseUrl . '/payments', $payload);

            if ($response->failed()) {
                Log::error('[RapdynService] Erro na criação de cobrança', [
                    'payload'   => $payload,
                    'response'  => $response->json(),
                ]);

                return [
                    'success' => false,
                    'error'   => $response->json('message')
                        ?? $response->json('error')
                        ?? 'Erro desconhecido na Rapdyn',
                    'details' => $response->json(),
                ];
            }

            $json = $response->json();

            return [
                'success'      => true,
                'data'         => $json,
                'provider_id'  => data_get($json, 'id'),
                'qr_code'      => data_get($json, 'pix.copypaste'),
                'qrcode_image' => data_get($json, 'pix.qrcode'),
                'status'       => data_get($json, 'status'),
            ];

        } catch (\Throwable $e) {
            Log::error('[RapdynService] Exceção na integração', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error'   => 'EXCEPTION_RAPDYN: ' . $e->getMessage(),
            ];
        }
    }
}
