<?php

namespace App\Services\TrustPay;

use App\Domain\Payments\Contracts\OutboundPaymentsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TrustPayLegacyOutboundProvider implements OutboundPaymentsProvider
{
    public function __construct(private array $config = [])
    {
        $this->config = array_merge([
            'base_url'        => rtrim((string) config('services.trustpay.base_url', ''), '/'),
            'email'           => config('services.trustpay.email'),
            'password'        => config('services.trustpay.password'),
            'timeout'         => (int) (config('services.trustpay.timeout') ?? 20),
            'connect_timeout' => (int) (config('services.trustpay.connect_timeout') ?? 5),
            'verify'          => (bool) (config('services.trustpay.verify') ?? true),
            'login_endpoint'  => config('services.trustpay.login_endpoint',  '/api/login'),
            'withdraw_endpoint' => config('services.trustpay.withdraw_endpoint', '/api/withdrawals'),
            'cache_key'       => 'trustpay:jwt',
            'cache_ttl'       => (int) (config('services.trustpay.token_cache_ttl') ?? 55 * 60),
        ], $this->config);

        if (!$this->config['base_url']) {
            throw new RuntimeException('TrustPay: base_url ausente.');
        }
        if (!$this->config['email'] || !$this->config['password']) {
            throw new RuntimeException('TrustPay: credenciais (email/password) ausentes.');
        }
    }

    protected function http()
    {
        return Http::withOptions([
            'timeout'         => $this->config['timeout'],
            'connect_timeout' => $this->config['connect_timeout'],
            'verify'          => $this->config['verify'],
        ])->acceptJson()->asJson();
    }

    protected function baseUrl(): string
    {
        return rtrim((string) $this->config['base_url'], '/');
    }

    /** Reaproveita o mesmo cache do inbound */
    protected function jwt(): string
    {
        return Cache::remember($this->config['cache_key'], $this->config['cache_ttl'], function () {
            $res = $this->http()
                ->retry(2, 300)
                ->post($this->baseUrl() . $this->config['login_endpoint'], [
                    'email'    => $this->config['email'],
                    'password' => $this->config['password'],
                ]);

            if ($res->failed()) {
                Log::warning('TrustPay OUT login falhou', ['status' => $res->status(), 'body' => $res->body()]);
                $res->throw();
            }

            $json = $res->json() ?? [];
            $token = $json['token'] ?? $json['access_token'] ?? data_get($json, 'data.token');

            if (!$token) {
                Log::error('TrustPay OUT: resposta de login sem token', ['json' => $json, 'body' => $res->body()]);
                throw new RuntimeException('TrustPay: resposta de login sem token.');
            }

            if (stripos($token, 'Bearer ') === 0) {
                $token = substr($token, 7);
            }

            return $token;
        });
    }

    /** Map interno -> enum da API */
    protected function mapPixKeyType(string $t): string
    {
        return match (strtolower($t)) {
            'cpf'   => 'CPF',
            'cnpj'  => 'CNPJ',
            'email' => 'EMAIL',
            'phone' => 'PHONE',
            'evp'   => 'EVP',
            default => 'EVP',
        };
    }

    /** Normaliza "9,25" -> 9.25 */
    protected function toFloatMixed($v): float
    {
        if (is_numeric($v)) return (float) $v;
        if (!is_string($v)) return 0.0;
        $v = str_replace(['.', ','], ['', '.'], $v); // "1.234,56" -> "1234.56" ; "9,25" -> "9.25"
        return (float) $v;
    }

    public function createPixWithdrawal(array $payload): array
    {
        // Campos esperados:
        // external_id, pixkey, pixkey_type, document_number, name, amount
        $required = ['external_id', 'pixkey', 'pixkey_type', 'document_number', 'name', 'amount'];
        foreach ($required as $r) {
            if (!isset($payload[$r]) || $payload[$r] === '' || $payload[$r] === null) {
                throw new RuntimeException('TrustPay(OUT): campos obrigatórios ausentes (pixkey, pixkey_type, document_number, name, amount).');
            }
        }

        $docDigits = preg_replace('/\D+/', '', (string) $payload['document_number']) ?? '';
        if ($docDigits === '') {
            throw new RuntimeException('TrustPay(OUT): document_number inválido.');
        }

        $body = [
            'externalId'     => (string) $payload['external_id'],
            'pixKey'         => (string) $payload['pixkey'],
            'pixKeyType'     => $this->mapPixKeyType((string) $payload['pixkey_type']),
            'documentNumber' => $docDigits,
            'name'           => (string) $payload['name'],
            'amount'         => number_format((float) $payload['amount'], 2, '.', ''), // sempre com ponto
        ];

        try {
            $jwt = $this->jwt();

            $res = $this->http()
                ->withHeaders(['Authorization' => 'Bearer ' . $jwt])
                ->retry(2, 300)
                ->post($this->baseUrl() . $this->config['withdraw_endpoint'], $body);

            if ($res->failed()) {
                Log::error('TrustPay OUT withdrawals falhou', ['status' => $res->status(), 'body' => $res->body()]);
                $res->throw();
            }

            $json = $res->json() ?? [];

            // Aceita tanto top-level quanto dentro de "data"
            $uuid      = data_get($json, 'data.id',    data_get($json, 'data.uuid',    data_get($json, 'uuid')));
            $extEcho   = data_get($json, 'data.externalId', data_get($json, 'externalId'));
            $amountStr = data_get($json, 'data.amount', data_get($json, 'amount'));
            $status    = strtolower((string) (data_get($json, 'data.status', data_get($json, 'status', 'pending'))));
            $createdAt = data_get($json, 'data.createdAt', data_get($json, 'createdAt'));

            if (!$uuid) {
                Log::error('TrustPay(OUT): resposta inválida em withdrawals', [
                    'status' => $res->status(),
                    'json'   => $json,
                    'body'   => $res->body(),
                ]);
                throw new RuntimeException('TrustPay(OUT): resposta inválida em withdrawals.');
            }

            $amountParsed = $this->toFloatMixed($amountStr);

            return [
                'provider'                 => 'trustpay',
                'provider_withdrawal_id'   => (string) $uuid,
                'external_id_echo'         => (string) ($extEcho ?? ''),
                'amount'                   => $amountParsed,
                'status'                   => $status, // 'pending' | 'processing' | 'completed' ...
                'created_at'               => $createdAt,
                'raw'                      => $json,
            ];

        } catch (ConnectionException $e) {
            throw new RuntimeException('TrustPay(OUT): falha de conexão (' . $e->getMessage() . ').', 0, $e);
        } catch (RequestException $e) {
            $code = optional($e->response)->status();
            Log::error('TrustPay(OUT) erro HTTP', ['status' => $code, 'msg' => $e->getMessage(), 'body' => $e->response?->body()]);
            throw new RuntimeException("TrustPay(OUT): erro HTTP (status {$code}): " . $e->getMessage(), 0, $e);
        }
    }
}
