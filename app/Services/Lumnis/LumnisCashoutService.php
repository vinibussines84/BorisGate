<?php

namespace App\Services\Lumnis;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LumnisCashoutService
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
     * 🔑 Obtém token de acesso (cacheado por 59 minutos)
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
                Log::error('❌ Falha na autenticação com a Lumnis (Cashout)', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                throw new \Exception('Falha na autenticação com a Lumnis API.');
            }

            return $response->json('access_token');
        });
    }

    /**
     * 💸 Cria um saque Pix (cashout)
     */
    public function createWithdrawal(array $payload): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ])
                ->post("{$this->baseUrl}/withdraw/pix", $payload);

            // Se o token expirou → reautentica
            if ($response->status() === 401) {
                Cache::forget('lumnis.access_token');

                $accessToken = $this->getAccessToken();

                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type'  => 'application/json',
                    ])
                    ->post("{$this->baseUrl}/withdraw/pix", $payload);
            }

            $data = $response->json() ?? [];

            if (!$response->successful()) {
                // Normaliza mensagem (caso venha como array)
                $msg = $data['message'] ?? $data['error'] ?? 'Erro Lumnis Cashout';
                if (is_array($msg)) {
                    $msg = implode('; ', $msg);
                }

                Log::error('❌ Erro Lumnis Cashout', [
                    'status'  => $response->status(),
                    'body'    => $data,
                    'payload' => $payload,
                ]);

                return [
                    'success' => false,
                    'status'  => $response->status(),
                    'message' => $msg,
                    'data'    => $data,
                ];
            }

            return [
                'success' => true,
                'status'  => $response->status(),
                'message' => $data['message'] ?? 'WITHDRAW_REQUEST',
                'data'    => $data,
            ];

        } catch (\Throwable $e) {
            Log::error('🚨 Exceção Lumnis Cashout', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'status'  => 500,
                'message' => 'Erro interno ao chamar Lumnis',
                'error'   => $e->getMessage(),
            ];
        }
    }
}
