<?php

namespace App\Services\VeltraxPay;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VeltraxPayClient
{
    public function __construct(
        private array $config = []
    ) {
        $this->config = array_replace([
            'base_url'      => config('services.veltraxpay.base_url', 'https://api.veltraxpay.com'),
            'client_id'     => config('services.veltraxpay.client_id'),
            'client_secret' => config('services.veltraxpay.client_secret'),
            'token_ttl'     => (int) config('services.veltraxpay.token_ttl', 55),
        ], $config);
    }

    public function deposit(array $payload): array
    {
        $token = $this->ensureToken();

        $resp = $this->http()
            ->withToken($token)
            ->post('/api/payments/deposit', $payload);

        $this->throwOnError($resp);

        return $resp->json() ?? [];
    }

    /* -------------------- internals -------------------- */

    private function ensureToken(): string
    {
        $key = $this->tokenKey();
        if ($token = Cache::get($key)) return $token;

        $resp = $this->http()->post('/api/auth/login', [
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]);

        $this->throwOnError($resp);

        $token = (string) data_get($resp->json(), 'token');
        if ($token === '') throw new \RuntimeException('VeltraxPay: token ausente na resposta.');

        $ttl = $this->ttlFromJwt($token) ?? ($this->config['token_ttl'] * 60);
        $ttl = max(60, (int) floor($ttl * 0.95)); // margem

        Cache::put($key, $token, now()->addSeconds($ttl));

        return $token;
    }

    private function http()
    {
        return Http::baseUrl(rtrim($this->config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->retry(3, 500, function ($e) {
                if ($e instanceof RequestException && $e->response) {
                    $s = $e->response->status();
                    return $s === 429 || ($s >= 500 && $s < 600);
                }
                return false;
            })
            ->timeout(20);
    }

    private function ttlFromJwt(string $jwt): ?int
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) return null;

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) return null;

        $data = json_decode($payload, true);
        if (!is_array($data) || !isset($data['exp'])) return null;

        $sec = (int) $data['exp'] - time();
        return $sec > 0 ? $sec : null;
    }

    private function tokenKey(): string
    {
        $id = $this->config['client_id'] ?: 'default';
        return "veltraxpay:token:{$id}";
    }

    private function throwOnError($resp): void
    {
        if ($resp->successful()) return;

        Log::warning('VeltraxPay HTTP error', [
            'status' => $resp->status(),
            'body'   => $resp->json() ?? $resp->body(),
        ]);

        $resp->throw();
    }
}
