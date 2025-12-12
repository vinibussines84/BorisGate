<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class ProviderXFlow
{
    protected string $baseUrl;
    protected int $timeout;
    protected string $callbackUrl;
    protected string $tokenCacheKey;

    public function __construct()
    {
        $this->baseUrl       = config('xflow.base_url');
        $this->timeout       = config('xflow.timeout', 10);
        $this->tokenCacheKey = config('xflow.token_cache_key', 'xflow_api_token');

        /**
         * 🔒 CALLBACK PIX (OBRIGATÓRIO)
         */
        $this->callbackUrl = config('xflow.callback_url_pix')
            ?? throw new Exception('XFlow callback_url_pix não configurada.');

        /**
         * 🔒 CREDENCIAIS
         */
        if (!config('xflow.client_id') || !config('xflow.client_secret')) {
            throw new Exception('Credenciais da XFlow não configuradas.');
        }
    }

    /**
     * 🔐 Retorna um token válido (cacheado e renovável)
     */
    protected function getToken(): string
    {
        return Cache::remember(
            $this->tokenCacheKey,
            now()->addMinutes(45), // margem segura antes do exp real
            function () {
                $response = Http::timeout($this->timeout)
                    ->post("{$this->baseUrl}/api/auth/login", [
                        'client_id'     => config('xflow.client_id'),
                        'client_secret' => config('xflow.client_secret'),
                    ]);

                if ($response->failed() || !$response->json('token')) {
                    Log::error('XFLOW_AUTH_FAILED', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);

                    throw new Exception('Erro ao autenticar na XFlow.');
                }

                return $response->json('token');
            }
        );
    }

    /**
     * 🚀 Client HTTP autenticado
     * - retry automático
     * - renova token se receber 401
     */
    protected function http()
    {
        return Http::withToken($this->getToken())
            ->withHeaders([
                'Connection' => 'keep-alive',
            ])
            ->timeout($this->timeout)
            ->retry(
                2,
                150,
                function ($exception) {
                    if ($exception->response?->status() === 401) {
                        Cache::forget($this->tokenCacheKey);
                    }
                }
            );
    }

    /**
     * 🧾 Criar PIX (Cash-in)
     */
    public function createPix(float $amount, array $data): array
    {
        $payload = [
            'amount'            => $amount,
            'external_id'       => $data['external_id'] ?? (string) Str::orderedUuid(),
            'clientCallbackUrl' => $data['clientCallbackUrl'] ?? $this->callbackUrl,
            'payer' => [
                'name'     => $data['payer']['name']     ?? 'Cliente',
                'email'    => $data['payer']['email']    ?? 'cliente@email.com',
                'document' => $data['payer']['document'] ?? null,
            ],
        ];

        if (app()->environment('local')) {
            Log::info('XFLOW_CREATE_PIX_REQUEST', [
                'payload' => $payload,
            ]);
        }

        $response = $this->http()
            ->post("{$this->baseUrl}/api/payments/deposit", $payload);

        if ($response->failed()) {
            Log::error('XFLOW_CREATE_PIX_FAILED', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new Exception('Erro ao criar PIX na XFlow.');
        }

        return $response->json();
    }

    /**
     * 🔍 Consultar status da transação PIX
     */
    public function getTransactionStatus(string $transactionId): array
    {
        $response = $this->http()
            ->get("{$this->baseUrl}/api/payments/{$transactionId}");

        if ($response->failed()) {
            Log::error('XFLOW_STATUS_FAILED', [
                'transaction_id' => $transactionId,
                'status'         => $response->status(),
                'body'           => $response->body(),
            ]);

            throw new Exception('Erro ao consultar transação XFlow.');
        }

        return $response->json();
    }

    /**
     * 📩 Processar Webhook (apenas log / ACK)
     */
    public function processWebhook(array $payload): array
    {
        Log::info('XFLOW_WEBHOOK_RECEIVED', [
            'payload' => $payload,
        ]);

        return [
            'status' => 'ok',
        ];
    }
}
