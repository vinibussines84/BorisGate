<?php

namespace App\Services\Rapdyn;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RapdynCashoutService
{
    protected string $baseUrl;
    protected string $token;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            config('services.rapdyn.base_url') ?? env('RAPDYN_BASE_URL', ''),
            '/'
        );

        $this->token = config('services.rapdyn.token') ?? env('RAPDYN_TOKEN', '');
        $this->timeout = (int) (config('services.rapdyn.timeout', 30));

        if (empty($this->baseUrl) || empty($this->token)) {
            throw new RuntimeException('❌ Variáveis RAPDYN_BASE_URL ou RAPDYN_TOKEN ausentes no .env');
        }
    }

    /**
     * Cria uma transferência PIX (saque) na Rapdyn
     */
    public function createCashout(array $payload, ?string $idempotencyKey = null): array
    {
        $url = "{$this->baseUrl}/transfers/out";

        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        try {
            Log::info('[RapdynCashout] Enviando POST', [
                'url'     => $url,
                'payload' => $payload,
            ]);

            $response = Http::withHeaders($headers)
                ->timeout($this->timeout)
                ->post($url, $payload);

            $body = $response->json();

            Log::info('[RapdynCashout] Resposta recebida', [
                'status' => $response->status(),
                'body'   => $body,
            ]);

            return [
                'success' => $response->successful(),
                'status'  => $response->status(),
                'data'    => $body,
            ];
        } catch (Throwable $e) {
            Log::error('[RapdynCashout] Falha na requisição', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return [
                'success' => false,
                'status'  => 500,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
