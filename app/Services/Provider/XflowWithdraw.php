<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class XflowWithdraw
{
    protected string $baseUrl;
    protected string $authEndpoint;
    protected int $timeout;
    protected int $retryTimes;
    protected int $retrySleep;
    protected string $callbackUrl;
    protected string $tokenCacheKey;

    public function __construct()
    {
        $this->baseUrl        = config('xflow.base_url');
        $this->authEndpoint  = config('xflow.auth_endpoint', '/api/auth/login');
        $this->timeout       = config('xflow.timeout', 10);
        $this->retryTimes    = config('xflow.retry_times', 2);
        $this->retrySleep    = config('xflow.retry_sleep', 150);
        $this->callbackUrl   = config('xflow.callback_url_withdraw');
        $this->tokenCacheKey = config('xflow.token_cache_key', 'xflow_api_token');

        if (!config('xflow.client_id') || !config('xflow.client_secret')) {
            throw new Exception('Credenciais da XFlow não configuradas.');
        }
    }

    /**
     * 🔐 Token válido (cacheado)
     */
    protected function getToken(): string
    {
        return Cache::remember(
            $this->tokenCacheKey,
            now()->addMinutes(45),
            function () {
                $response = Http::timeout($this->timeout)->post(
                    "{$this->baseUrl}{$this->authEndpoint}",
                    [
                        'client_id'     => config('xflow.client_id'),
                        'client_secret' => config('xflow.client_secret'),
                    ]
                );

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
     * 🚀 HTTP autenticado
     */
    protected function http()
    {
        return Http::withToken($this->getToken())
            ->withHeaders([
                'Connection' => 'keep-alive',
            ])
            ->timeout($this->timeout)
            ->retry(
                $this->retryTimes,
                $this->retrySleep,
                function ($exception) {
                    if ($exception->response?->status() === 401) {
                        Cache::forget($this->tokenCacheKey);
                    }
                }
            );
    }

    /**
     * 💸 Criar saque PIX (XFlow)
     *
     * Espera SEMPRE:
     * - key
     * - key_type
     */
    public function withdraw(float $amount, array $data): array
    {
        // 🔒 Validação do domínio (CORRETA)
        foreach (['key', 'key_type'] as $field) {
            if (empty($data[$field])) {
                throw new Exception("Campo obrigatório ausente: {$field}");
            }
        }

        // 🔁 Conversão para formato XFlow
        $payload = [
            'amount'            => $amount,
            'external_id'       => $data['external_id'] ?? (string) Str::orderedUuid(),
            'pix_key'           => $data['key'], // ✅ conversão AQUI
            'key_type'          => strtoupper($data['key_type']), // EMAIL | CPF | CNPJ | PHONE
            'description'       => $data['description'] ?? 'Saque solicitado',
            'clientCallbackUrl' => $this->callbackUrl,
        ];

        if (app()->environment('local')) {
            Log::info('XFLOW_WITHDRAW_REQUEST', $payload);
        }

        $response = $this->http()->post(
            "{$this->baseUrl}/api/withdrawals/withdraw",
            $payload
        );

        if ($response->failed()) {
            Log::error('XFLOW_WITHDRAW_FAILED', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new Exception('Erro ao criar saque na XFlow.');
        }

        return $response->json();
    }
}
