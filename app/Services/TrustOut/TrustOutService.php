<?php
declare(strict_types=1);

namespace App\Services\TrustOut;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class TrustOutService
{
    /** Documento fixo (CPF/CNPJ) exigido para saques */
    private const FIXED_DOCUMENT_NUMBER = '28343827007';

    private string $baseUrl;
    private string $email;
    private string $password;
    private string $loginEndpoint;
    private string $withdrawEndpoint;
    private int $timeout;
    private int $connectTimeout;
    private string $cacheKey;

    public function __construct(array $config = [])
    {
        $cfg = array_replace([
            // Reutiliza o mesmo bloco de config do TrustIn
            'base_url'          => rtrim((string) config('services.trustin.base_url', ''), '/'),
            'email'             => (string) config('services.trustin.email'),
            'password'          => (string) config('services.trustin.password'),
            'login_endpoint'    => (string) config('services.trustin.login_endpoint', '/api/login'),
            'withdraw_endpoint' => (string) config('services.trustin.withdraw_endpoint', '/api/withdrawals'),
            'timeout'           => (int) config('services.trustin.timeout', 15),
            'connect_timeout'   => (int) config('services.trustin.connect_timeout', 5),
            'token_cache_key'   => (string) config('services.trustin.token_cache_key', 'trustin.jwt.token'),
        ], $config);

        $this->baseUrl          = $cfg['base_url'];
        $this->email            = $cfg['email'];
        $this->password         = $cfg['password'];
        $this->loginEndpoint    = $cfg['login_endpoint'];
        $this->withdrawEndpoint = $cfg['withdraw_endpoint'];
        $this->timeout          = $cfg['timeout'];
        $this->connectTimeout   = $cfg['connect_timeout'];
        $this->cacheKey         = $cfg['token_cache_key'];
    }

    /** Obtém JWT (cacheado) para endpoints legados. */
    public function getJwtToken(bool $forceRefresh = false): string
    {
        if (!$forceRefresh) {
            $cached = Cache::get($this->cacheKey);
            if (is_string($cached) && $cached !== '') return $cached;
        }

        $url = $this->url($this->loginEndpoint);

        try {
            $res = Http::timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'email'    => $this->email,
                    'password' => $this->password,
                ])
                ->throw();
        } catch (ConnectionException $e) {
            Log::error('TrustOut login connection error', ['e' => $e->getMessage()]);
            throw new RuntimeException('Falha de conexão no login TrustOut.');
        } catch (RequestException $e) {
            Log::warning('TrustOut login request failed', [
                'status' => optional($e->response)->status(),
                'body'   => optional($e->response)->json(),
            ]);
            throw new RuntimeException('Login TrustOut retornou erro.');
        }

        $json  = (array) $res->json();
        $token = (string) ($json['token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Token ausente no login TrustOut.');
        }

        // TTL baseado em expires_at (menos 60s); fallback 25 min
        $ttlSeconds = 1500;
        if (!empty($json['expires_at'])) {
            try {
                $exp = CarbonImmutable::parse((string) $json['expires_at']);
                $ttlSeconds = max(60, $exp->diffInSeconds(CarbonImmutable::now()) - 60);
            } catch (\Throwable) {}
        }

        Cache::put($this->cacheKey, $token, now()->addSeconds($ttlSeconds));
        return $token;
    }

    /**
     * Cria pedido de saque no endpoint legado /api/withdrawals.
     *
     * Parâmetros esperados (mas o documentNumber será SEMPRE fixo):
     * - externalId       (string)  obrigatório
     * - pixKey           (string)  obrigatório
     * - pixKeyType       (CPF|CNPJ|EMAIL|PHONE|EVP)  obrigatório (case-insensitive)
     * - name             (string)  obrigatório
     * - amount           (number)  obrigatório
     * - idempotencyKey   (string)  opcional (manda em header)
     */
    public function createWithdrawal(array $payload): array
    {
        // Checa os campos que NÃO são o documento
        foreach (['externalId','pixKey','pixKeyType','name','amount'] as $req) {
            if (!array_key_exists($req, $payload)) {
                throw new RuntimeException("Campo obrigatório ausente: {$req}");
            }
        }

        // Normaliza enum do tipo de chave Pix
        $pixKeyType = strtoupper((string) $payload['pixKeyType']);
        $allowed    = ['CPF','CNPJ','EMAIL','PHONE','EVP'];
        if (!in_array($pixKeyType, $allowed, true)) {
            throw new RuntimeException('pixKeyType inválido. Use: CPF, CNPJ, EMAIL, PHONE ou EVP.');
        }
        $payload['pixKeyType'] = $pixKeyType;

        // Documento **fixo** (ignora qualquer coisa vinda do caller/front)
        $doc = preg_replace('/\D+/', '', self::FIXED_DOCUMENT_NUMBER) ?? '';
        if (!preg_match('/^\d{11}$|^\d{14}$/', $doc)) {
            throw new RuntimeException('Documento fixo inválido (deveria ter 11 ou 14 dígitos).');
        }

        // Log seguro (mascarado)
        try {
            Log::info('TrustOut createWithdrawal (safe)', [
                'externalId'    => (string) $payload['externalId'],
                'pixKeyType'    => $payload['pixKeyType'],
                'pixKey_len'    => strlen((string) $payload['pixKey']),
                'amount'        => (float) $payload['amount'],
                'document_mask' => substr($doc,0,3).'***'.substr($doc,-2),
            ]);
        } catch (\Throwable) {}

        $token = $this->getJwtToken();
        $url   = $this->url($this->withdrawEndpoint);

        $doRequest = function (string $jwt) use ($url, $payload, $doc) {
            $req = Http::timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->acceptJson()
                ->asJson()
                ->withToken($jwt);

            // Header de idempotência (se informado)
            if (!empty($payload['idempotencyKey'] ?? '')) {
                $req = $req->withHeaders(['Idempotency-Key' => (string) $payload['idempotencyKey']]);
            }

            // Corpo: envia APENAS documentNumber (sem aliases)
            return $req->post($url, [
                'externalId'     => (string) $payload['externalId'],
                'pixKey'         => (string) $payload['pixKey'],
                'pixKeyType'     => (string) $payload['pixKeyType'],
                'documentNumber' => $doc,
                'name'           => (string) $payload['name'],
                'amount'         => (float)  $payload['amount'],
            ]);
        };

        try {
            $res = $doRequest($token);

            // Reautentica se token expirar
            if ($res->status() === 401) {
                $token = $this->getJwtToken(true);
                $res   = $doRequest($token);
            }

            $res->throw();
        } catch (ConnectionException $e) {
            Log::error('TrustOut withdrawals connection error', ['e' => $e->getMessage()]);
            throw new RuntimeException('Falha de conexão ao criar saque TrustOut.');
        } catch (RequestException $e) {
            Log::warning('TrustOut withdrawals failed', [
                'status' => optional($e->response)->status(),
                'body'   => optional($e->response)->json(),
            ]);
            $msg  = 'Erro ao criar saque TrustOut.';
            $body = $e->response?->json();
            if (is_array($body) && isset($body['message'])) {
                $msg .= ' '.$body['message'];
            }
            throw new RuntimeException($msg);
        }

        return (array) $res->json();
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
