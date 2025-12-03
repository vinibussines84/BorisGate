<?php

namespace App\Services\Pluggou;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PluggouWithdrawService
{
    protected string $baseUrl;
    protected ?string $publicKey;
    protected ?string $secretKey;

    public function __construct()
    {
        $this->baseUrl   = rtrim(config('services.pluggou.base_url', 'https://api.pluggoutech.com/api'), '/');
        $this->publicKey = config('services.pluggou.public_key');
        $this->secretKey = config('services.pluggou.secret_key');
    }

    /**
     * Cria um saque na Pluggou.
     *
     * Espera:
     * - amount em CENTAVOS (int)
     * - key_type: cpf | cnpj | phone | email | evp
     * - key_value: já normalizado
     */
    public function createWithdrawal(array $payload, ?string $idempotencyKey = null): array
    {
        $url = "{$this->baseUrl}/withdrawals";

        $headers = [
            'X-Public-Key' => $this->publicKey,
            'User-Agent'   => 'EquitPay/1.0 (Laravel WithdrawService)',
        ];

        if (!empty($this->secretKey)) {
            $headers['X-Secret-Key'] = $this->secretKey;
        }

        if (!empty($idempotencyKey)) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        // Corpo da requisição
        $body = [
            'amount'    => (int) $payload['amount'], // já em centavos
            'key_type'  => $payload['key_type'],
            'key_value' => $payload['key_value'],
        ];

        // Logs seguros
        $safeHeaders = $headers;
        unset($safeHeaders['X-Secret-Key']);

        $maskedBody = $body;
        if (isset($maskedBody['key_value']) && strlen($maskedBody['key_value']) > 6) {
            $v = $maskedBody['key_value'];
            $maskedBody['key_value'] = substr($v, 0, 3) . '****' . substr($v, -3);
        }

        try {
            $start = microtime(true);

            $resp = Http::timeout(20)
                ->retry(2, 300)
                ->withHeaders($headers)
                ->post($url, $body);

            $duration = round((microtime(true) - $start) * 1000, 2);
            $json = $resp->json() ?? [];

            // Garante sempre array
            if (!is_array($json)) {
                $json = ['message' => 'Resposta inválida da API'];
            }

            Log::info('[Pluggou Withdraw] HTTP Response', [
                'url'      => $url,
                'status'   => $resp->status(),
                'time_ms'  => $duration,
                'headers'  => $safeHeaders,
                'request'  => $maskedBody,
                'response' => $json,
            ]);

            // Extrai erros corretamente sem warnings
            $errors = null;
            if (isset($json['data']['errors'])) {
                $errors = $json['data']['errors'];
            }

            return [
                'success'           => $resp->successful(),
                'status'            => $resp->status(),
                'data'              => $json,
                'validation_errors' => $errors,
            ];

        } catch (\Throwable $e) {

            Log::error('[Pluggou Withdraw] Exception', [
                'url'     => $url,
                'headers' => $safeHeaders,
                'payload' => $maskedBody,
                'error'   => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status'  => 500,
                'data'    => [
                    'message'   => 'Erro inesperado ao chamar API.',
                    'exception' => $e->getMessage(),
                ],
            ];
        }
    }
}
