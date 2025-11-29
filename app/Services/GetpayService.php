<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GetpayService
{
    /** Token cache TTL em segundos (~50min) */
    protected int $tokenTtl = 3000;

    /** Base URL padrão (fallback) */
    protected string $defaultBaseUrl = 'https://hub.getpay.store';

    /** ---------------------------------------------------------------------
     * Helpers de config/cache/client
     * ------------------------------------------------------------------- */
    protected function tokenCacheKey(Provider $provider): string
    {
        return "getpay:provider:{$provider->id}:jwt";
    }

    /** Lê chave do config do Provider (KeyValue no Filament) */
    protected function cfg(Provider $provider, string $key, $default = null)
    {
        $cfg = (array) ($provider->config ?? []);
        return Arr::get($cfg, $key, $default);
    }

    /** Monta base URL (com fallback) */
    protected function baseUrl(Provider $provider): string
    {
        $base = trim((string) $this->cfg($provider, 'base_url', $this->defaultBaseUrl));
        if ($base === '') $base = $this->defaultBaseUrl;
        return rtrim($base, '/');
    }

    /** Cria client HTTP (timeout + headers) */
    protected function client(Provider $provider, ?string $token = null)
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return Http::baseUrl($this->baseUrl($provider))
            ->withHeaders($headers)
            ->timeout(30);
    }

    /** Checa se a resposta aparenta ser JSON válido */
    protected function responseLooksJson($resp): bool
    {
        $ct = (string) $resp->header('Content-Type', '');
        if (str_contains(strtolower($ct), 'application/json')) {
            return true;
        }
        // fallback: tenta decodificar
        $raw = (string) $resp->body();
        if ($raw === '') return false;
        try {
            json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Faz login e guarda o token no cache */
    protected function loginAndCacheToken(Provider $provider): ?string
    {
        $email    = (string) $this->cfg($provider, 'email');
        $password = (string) $this->cfg($provider, 'password');

        if ($email === '' || $password === '') {
            return null;
        }

        $resp = $this->client($provider)->post('/api/login', [
            'email'    => $email,
            'password' => $password,
        ]);

        // Se voltou HTML/iframe (API offline), falha
        if (!$resp->successful() || !$this->responseLooksJson($resp)) {
            return null;
        }

        $json  = $resp->json() ?? [];
        // Alguns backends retornam "token", outros "access_token", "jwt", etc.
        $token = $json['token'] ?? $json['access_token'] ?? $json['jwt'] ?? null;

        if ($token) {
            Cache::put($this->tokenCacheKey($provider), $token, $this->tokenTtl);
        }

        return $token;
    }

    /** Obtém token do cache ou faz login */
    protected function getToken(Provider $provider): ?string
    {
        $key   = $this->tokenCacheKey($provider);
        $token = Cache::get($key);
        if ($token) {
            return $token;
        }
        return $this->loginAndCacheToken($provider);
    }

    /** ---------------------------------------------------------------------
     * Cash-in (PIX) — create-payment
     * ------------------------------------------------------------------- */
    /**
     * @param  User     $user      Dono da transação
     * @param  Provider $provider  Provider configurado no usuário (cashin)
     * @param  array    $context   externalId/idempotency_key, amount, document, name, identification, expire, description, transaction_id...
     * @return array    [
     *   ok, http_status, flow, provider, user_id, message,
     *   data: { request, response, provider_transaction_id, txid, e2e_id }
     * ]
     */
    public function executeIn(User $user, Provider $provider, array $context = []): array
    {
        try {
            /** 1) Token */
            $token = $this->getToken($provider);
            if (!$token) {
                return [
                    'ok'         => false,
                    'http_status'=> 0,
                    'flow'       => 'providerIn',
                    'provider'   => (string) ($provider->code ?? 'getpay'),
                    'user_id'    => $user->id,
                    'message'    => 'Falha de autenticação no provider (token ausente).',
                    'data'       => [],
                ];
            }

            /** 2) Payload */
            $amount = (float) ($context['amount'] ?? 0);
            if ($amount <= 0) {
                return [
                    'ok'         => false,
                    'http_status'=> 0,
                    'flow'       => 'providerIn',
                    'provider'   => (string) ($provider->code ?? 'getpay'),
                    'user_id'    => $user->id,
                    'message'    => 'Valor inválido (amount <= 0).',
                    'data'       => [],
                ];
            }

            $externalId     = (string) ($context['externalId'] ?? $context['idempotency_key'] ?? ('tx-' . ($context['transaction_id'] ?? Str::uuid())));
            $document       = (string) ($context['document'] ?? ($user->document ?? '00000000000'));
            $name           = (string) ($context['name'] ?? ($user->nome_completo ?? 'Cliente'));
            $identification = (string) ($context['identification'] ?? ($context['transaction_id'] ?? Str::upper(Str::random(8))));
            $expire         = (int)    ($context['expire'] ?? 3600);
            $description    = (string) ($context['description'] ?? 'Depósito via PIX');

            $requestBody = [
                'externalId'     => $externalId,
                'amount'         => $amount,
                'document'       => $document,
                'name'           => $name,
                'identification' => $identification,
                'expire'         => $expire,
                'description'    => $description,
            ];

            /** 3) POST create-payment */
            $resp = $this->client($provider, $token)->post('/api/create-payment', $requestBody);

            /** 4) Se 401 → renovar token e tentar 1x */
            if ($resp->status() === 401) {
                $token = $this->loginAndCacheToken($provider);
                if (!$token) {
                    return [
                        'ok'         => false,
                        'http_status'=> 401,
                        'flow'       => 'providerIn',
                        'provider'   => (string) ($provider->code ?? 'getpay'),
                        'user_id'    => $user->id,
                        'message'    => 'Token expirado e não foi possível renovar.',
                        'data'       => ['request' => $requestBody],
                    ];
                }
                $resp = $this->client($provider, $token)->post('/api/create-payment', $requestBody);
            }

            $status = $resp->status();
            $looksJson = $this->responseLooksJson($resp);
            $json = $looksJson ? ($resp->json() ?? []) : [];

            /** 5) Falha → retornar com http_status e eco do request/erro */
            if (!$resp->successful() || !$looksJson) {
                return [
                    'ok'         => false,
                    'http_status'=> $status,
                    'flow'       => 'providerIn',
                    'provider'   => (string) ($provider->code ?? 'getpay'),
                    'user_id'    => $user->id,
                    'message'    => 'Falha ao criar pagamento PIX na Getpay.',
                    'data'       => [
                        'status'  => $status,
                        'error'   => $looksJson ? $json : ['non_json' => true, 'length' => strlen((string)$resp->body())],
                        'request' => $requestBody,
                    ],
                ];
            }

            /** 6) Extrair possíveis IDs de conciliação */
            $providerTransactionId = Arr::get($json, 'id')
                ?? Arr::get($json, 'transactionId')
                ?? Arr::get($json, 'data.id');

            $txid   = Arr::get($json, 'txid')   ?? Arr::get($json, 'data.txid');
            $e2e_id = Arr::get($json, 'e2e_id') ?? Arr::get($json, 'data.e2e_id');

            return [
                'ok'         => true,
                'http_status'=> $status,
                'flow'       => 'providerIn',
                'provider'   => (string) ($provider->code ?? 'getpay'),
                'user_id'    => $user->id,
                'message'    => 'Execução de entrada realizada com sucesso.',
                'data'       => [
                    'request'                 => $requestBody,
                    'response'                => $json,
                    'provider_transaction_id' => $providerTransactionId,
                    'txid'                    => $txid,
                    'e2e_id'                  => $e2e_id,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok'         => false,
                'http_status'=> 0,
                'flow'       => 'providerIn',
                'provider'   => (string) ($provider->code ?? 'getpay'),
                'user_id'    => $user->id,
                'message'    => 'Exceção ao comunicar com o provider.',
                'data'       => [
                    'error' => class_basename($e) . ': ' . $e->getMessage(),
                ],
            ];
        }
    }

    /** ---------------------------------------------------------------------
     * Cash-out (esqueleto)
     * ------------------------------------------------------------------- */
    public function executeOut(User $user, Provider $provider, array $context = []): array
    {
        return [
            'ok'         => false,
            'http_status'=> 0,
            'flow'       => 'providerOut',
            'provider'   => (string) ($provider->code ?? 'getpay'),
            'user_id'    => $user->id,
            'message'    => 'Cash-out não implementado para Getpay.',
            'data'       => [],
        ];
    }
}
