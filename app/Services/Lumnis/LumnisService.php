<?php

namespace App\Services\Lumnis;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
     * 🔑 Obtém token com cache de 50 minutos
     */
    protected function getAccessToken(): ?string
    {
        return Cache::remember('lumnis.access_token', now()->addMinutes(50), function () {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept'       => 'application/json',
                    ])
                    ->post("{$this->baseUrl}/auth/token", [
                        'code'  => $this->code,
                        'token' => $this->token,
                    ]);

                if ($response->failed()) {
                    Log::error('LUMNIS_AUTH_FAILED', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    throw new \Exception("Falha na autenticação: " . $response->body());
                }

                $token = $response->json('access_token');

                if (!$token) {
                    Log::error('LUMNIS_AUTH_NO_TOKEN', [
                        'response' => $response->body(),
                    ]);
                    throw new \Exception("Token não encontrado: " . $response->body());
                }

                return $token;

            } catch (\Throwable $e) {
                throw new \Exception("Erro ao obter token Lumnis: " . $e->getMessage());
            }
        });
    }

    /**
     * 🚀 Cria uma transação PIX na Lumnis
     */
    public function createTransaction(array $payload): array
    {
        Log::info('LUMNIS_ENVIANDO_PAYLOAD', $payload);

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])
                ->post("{$this->baseUrl}/transaction", $payload);

        } catch (\Throwable $e) {
            Log::error("LUMNIS_HTTP_ERROR", [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'status' => 500,
                'body'   => ['error' => "Falha na comunicação com a Lumnis: " . $e->getMessage()],
            ];
        }

        /**
         * 🔄 Se o token expirou → renovar e reenviar
         */
        if ($response->status() === 401) {

            Log::warning("LUMNIS_TOKEN_EXPIRED");

            Cache::forget('lumnis.access_token');

            try {

                $accessToken = $this->getAccessToken();

                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ])
                    ->post("{$this->baseUrl}/transaction", $payload);

            } catch (\Throwable $e) {
                Log::error("LUMNIS_RETRY_FAILED", [
                    'error'   => $e->getMessage(),
                    'payload' => $payload,
                ]);

                return [
                    'status' => 500,
                    'body'   => ['error' => "Falha ao reenviar com novo token: " . $e->getMessage()],
                ];
            }
        }

        /**
         * 📦 Capturar body mesmo quando não é JSON
         */
        $body = null;

        try {
            $body = $response->json();
        } catch (\Throwable) {
            $body = $response->body();
        }

        /**
         * 🟥 Logar erro se retornar 400/422/500
         */
        if ($response->failed()) {
            Log::error('LUMNIS_ERRO_RESPOSTA', [
                'status'  => $response->status(),
                'body'    => $body,
                'payload' => $payload,
            ]);
        }

        return [
            'status' => $response->status(),
            'body'   => $body,
        ];
    }
}
