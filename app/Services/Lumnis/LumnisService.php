<?php

namespace App\Services\Lumnis;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class LumnisService
{
    protected string $baseUrl;
    protected string $code;
    protected string $token;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.lumnis.base_url', 'https://api.lumnisolucoes.com.br'), '/');
        $this->code    = config('services.lumnis.code');
        $this->token   = config('services.lumnis.token');
        $this->timeout = (int) config('services.lumnis.timeout', 15);
    }

    /**
     * 🔑 Obtém token de acesso com cache (50 minutos)
     */
    protected function getAccessToken(): ?string
    {
        return Cache::remember('lumnis.access_token', now()->addMinutes(50), function () {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("{$this->baseUrl}/auth/token", [
                        'code'  => $this->code,
                        'token' => $this->token,
                    ]);

                if ($response->failed()) {
                    throw new \Exception("Falha na autenticação: " . $response->body());
                }

                $token = $response->json('access_token');

                if (!$token) {
                    throw new \Exception("Token não encontrado na resposta: " . $response->body());
                }

                return $token;

            } catch (\Throwable $e) {
                throw new \Exception("Erro ao obter token Lumnis: " . $e->getMessage());
            }
        });
    }

    /**
     * 🚀 Cria uma transação PIX
     */
    public function createTransaction(array $payload): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ])
                ->post("{$this->baseUrl}/transaction", $payload);

        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body'   => ['error' => "Falha na comunicação com Lumnis: " . $e->getMessage()],
            ];
        }

        /**
         * 🔄 Token expirado → tenta novamente
         */
        if ($response->status() === 401) {
            Cache::forget('lumnis.access_token');

            try {
                $accessToken = $this->getAccessToken();

                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type'  => 'application/json',
                    ])
                    ->post("{$this->baseUrl}/transaction", $payload);

            } catch (\Throwable $e) {
                return [
                    'status' => 500,
                    'body'   => ['error' => "Falha ao reenviar com novo token: " . $e->getMessage()],
                ];
            }
        }

        /**
         * 📦 Retorno com tratamento para JSON inválido
         */
        $body = null;

        try {
            $body = $response->json();
        } catch (\Throwable) {
            $body = $response->body(); // pode ser texto/HTML
        }

        return [
            'status' => $response->status(),
            'body'   => $body,
        ];
    }
}
