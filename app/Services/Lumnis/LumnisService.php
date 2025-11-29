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
        $this->baseUrl = config('services.lumnis.base_url', 'https://api.lumnisolucoes.com.br');
        $this->code    = config('services.lumnis.code');
        $this->token   = config('services.lumnis.token');
        $this->timeout = (int) config('services.lumnis.timeout', 15);
    }

    /**
     * 🔑 Obtém token de acesso com cache automático (59 min)
     */
    protected function getAccessToken(): ?string
    {
        return Cache::remember('lumnis.access_token', now()->addMinutes(59), function () {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/auth/token", [
                    'code'  => $this->code,
                    'token' => $this->token,
                ]);

            if ($response->failed()) {
                throw new \Exception('Falha na autenticação com a Lumnis API.');
            }

            return $response->json('access_token');
        });
    }

    /**
     * 🚀 Cria uma transação PIX
     */
    public function createTransaction(array $payload): array
    {
        $accessToken = $this->getAccessToken();

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type'  => 'application/json',
            ])
            ->post("{$this->baseUrl}/transaction", $payload);

        // Se expirou o token → reautentica automaticamente
        if ($response->status() === 401) {
            Cache::forget('lumnis.access_token');

            $accessToken = $this->getAccessToken();

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ])
                ->post("{$this->baseUrl}/transaction", $payload);
        }

        return [
            'status' => $response->status(),
            'body'   => $response->json(),
        ];
    }
}
