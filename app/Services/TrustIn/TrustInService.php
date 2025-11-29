<?php
declare(strict_types=1);

namespace App\Services\TrustIn;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class TrustInService
{
    private string $baseUrl;
    private string $email;
    private string $password;
    private string $loginEndpoint;
    private string $createPaymentEndpoint;
    private int $timeout;
    private int $connectTimeout;
    private string $cacheKey;

    public function __construct(array $config = [])
    {
        $cfg = array_replace([
            'base_url'                => rtrim((string) config('services.trustin.base_url', ''), '/'),
            'email'                   => (string) config('services.trustin.email'),
            'password'                => (string) config('services.trustin.password'),
            'login_endpoint'          => (string) config('services.trustin.login_endpoint', '/api/login'),
            'create_payment_endpoint' => (string) config('services.trustin.create_payment_endpoint', '/api/create-payment'),
            'timeout'                 => (int) config('services.trustin.timeout', 15),
            'connect_timeout'         => (int) config('services.trustin.connect_timeout', 5),
            'token_cache_key'         => (string) config('services.trustin.token_cache_key', 'trustin.jwt'),
        ], $config);

        $this->baseUrl               = $cfg['base_url'];
        $this->email                 = $cfg['email'];
        $this->password              = $cfg['password'];
        $this->loginEndpoint         = $cfg['login_endpoint'];
        $this->createPaymentEndpoint = $cfg['create_payment_endpoint'];
        $this->timeout               = $cfg['timeout'];
        $this->connectTimeout        = $cfg['connect_timeout'];
        $this->cacheKey              = $cfg['token_cache_key'];
    }

    /**
     * Retorna JWT válido (busca do cache; renova se necessário).
     */
    public function getJwtToken(bool $forceRefresh = false): string
    {
        if (!$forceRefresh) {
            $cached = Cache::get($this->cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
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
            Log::error('TrustIn login connection error', ['e' => $e->getMessage()]);
            throw new RuntimeException('Falha de conexão no login TrustIn.');
        } catch (RequestException $e) {
            Log::warning('TrustIn login request failed', [
                'status' => optional($e->response)->status(),
                'body'   => optional($e->response)->json(),
            ]);
            throw new RuntimeException('Login TrustIn retornou erro.');
        }

        $json = (array) $res->json();
        $token = (string) ($json['token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Token ausente no login TrustIn.');
        }

        // TTL baseado em expires_at - 60s (fallback: 25 min)
        $ttlSeconds = 1500; // 25 minutos
        if (!empty($json['expires_at'])) {
            try {
                $exp = CarbonImmutable::parse((string) $json['expires_at']);
                $ttlSeconds = max(60, $exp->diffInSeconds(CarbonImmutable::now()) - 60);
            } catch (\Throwable) {
                // mantém fallback
            }
        }

        Cache::put($this->cacheKey, $token, now()->addSeconds($ttlSeconds));
        return $token;
    }

    /**
     * Cria pagamento legado (/api/create-payment) com retry automático em 401.
     * Campos obrigatórios:
     * - externalId (string)
     * - amount (number)
     * - document (string)
     * - name (string)
     * - expire (number, em segundos)
     * Campo opcional: identification (string)
     */
    public function createPayment(array $payload): array
    {
        // validações rápidas
        foreach (['externalId','amount','document','name','expire'] as $req) {
            if (!array_key_exists($req, $payload)) {
                throw new RuntimeException("Campo obrigatório ausente: {$req}");
            }
        }

        $token = $this->getJwtToken();
        $url   = $this->url($this->createPaymentEndpoint);

        $doRequest = function (string $jwt) use ($url, $payload) {
            return Http::timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->acceptJson()
                ->asJson()
                ->withToken($jwt) // Authorization: Bearer <token>
                ->post($url, [
                    'externalId'    => (string) $payload['externalId'],
                    'amount'        => $payload['amount'],
                    'document'      => (string) $payload['document'],
                    'name'          => (string) $payload['name'],
                    'expire'        => $payload['expire'],
                    'identification'=> $payload['identification'] ?? null,
                ]);
        };

        try {
            $res = $doRequest($token);
            if ($res->status() === 401) {
                // token expirado/ inválido -> força refresh e tenta de novo
                $token = $this->getJwtToken(true);
                $res   = $doRequest($token);
            }
            $res->throw();
        } catch (ConnectionException $e) {
            Log::error('TrustIn create-payment connection error', ['e' => $e->getMessage()]);
            throw new RuntimeException('Falha de conexão ao criar pagamento TrustIn.');
        } catch (RequestException $e) {
            Log::warning('TrustIn create-payment failed', [
                'status' => optional($e->response)->status(),
                'body'   => optional($e->response)->json(),
            ]);
            $msg = 'Erro ao criar pagamento TrustIn.';
            $body = $e->response?->json();
            if (is_array($body) && isset($body['message'])) {
                $msg .= ' '.$body['message'];
            }
            throw new RuntimeException($msg);
        }

        $json = (array) $res->json();
        return $json;
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
