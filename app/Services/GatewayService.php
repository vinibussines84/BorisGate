<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class GatewayService
{
    protected string $base;
    protected string $clientId;
    protected string $clientSecret;
    protected string $callback;
    protected int $timeout;

    public function __construct()
    {
        $base = (string) config('services.gateway.base_url', '');
        $base = trim($base);

        // corrige casos "=/https://..." ou espaços
        if ($base !== '' && str_starts_with($base, '=')) {
            $base = ltrim($base, '=');
        }

        // remove barras finais repetidas
        $base = rtrim($base, "/ \t\n\r\0\x0B");

        $this->base         = $base;
        $this->clientId     = (string) config('services.gateway.client_id');
        $this->clientSecret = (string) config('services.gateway.client_secret');
        $this->callback     = (string) config('services.gateway.callback_url');
        $this->timeout      = (int) config('services.gateway.timeout', 25);

        // validação forte
        if ($this->base === '' || !preg_match('#^https?://#i', $this->base)) {
            Log::error('[Gateway] base_url inválida', ['base_url' => $this->base]);
            throw new RuntimeException('Configuração inválida: services.gateway.base_url deve iniciar com http(s)://');
        }

        // log de sanity
        Log::debug('[Gateway] Base URL carregada', ['base_url' => $this->base]);
    }

    protected function http()
    {
        return Http::withOptions([
            // não uso base_uri pra evitar ambiguidades — vou mandar URLs absolutas
            'timeout' => $this->timeout,
        ])->acceptJson();
    }

    /** Autentica e retorna token JWT */
    public function authenticate(): string
    {
        $url = $this->base . '/api/auth/login';
        Log::debug('[Gateway] POST ' . $url);

        $resp = $this->http()
            ->asJson()
            ->post($url, [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
            ])
            ->throw();

        $json  = $resp->json();
        $token = (string) data_get($json, 'token', '');

        if ($token === '') {
            throw new RuntimeException('Token não retornado pelo gateway.');
        }

        return $token;
    }

    /** Cria depósito PIX e retorna o JSON do gateway */
    public function createDeposit(array $data): array
    {
        $token = $this->authenticate();

        $body = [
            'amount'            => (float) data_get($data, 'amount'),
            'external_id'       => (string) data_get($data, 'external_id', (string) Str::orderedUuid()),
            'clientCallbackUrl' => $this->callback,
            'payer'             => [
                'name'     => (string) data_get($data, 'payer.name'),
                'email'    => (string) data_get($data, 'payer.email'),
                'document' => (string) data_get($data, 'payer.document'),
                'phone'    => (string) data_get($data, 'payer.phone'),
            ],
        ];

        $url = $this->base . '/api/payments/deposit';
        Log::debug('[Gateway] POST ' . $url, [
            'has_token' => $token !== '',
            'amount'    => $body['amount'],
            'external'  => $body['external_id'],
        ]);

        $resp = $this->http()
            ->withToken($token)
            ->asJson()
            ->post($url, $body)
            ->throw();

        $json   = $resp->json();
        $code   = (string) data_get($json, 'code', '');
        $qrcode = (string) data_get($json, 'qrCodeResponse.qrcode', '');

        $successCodes = ['DEPOSITO_CRIADO', 'OK', 'SUCCESS'];
        if (in_array($code, $successCodes, true)) {
            if ($qrcode === '') {
                Log::warning('[Gateway] Sucesso sem qrcode em qrCodeResponse', ['code' => $code, 'json' => $json]);
            }
            return $json;
        }

        throw new RuntimeException('Resposta inesperada do gateway: ' . json_encode($json));
    }
}
