<?php

namespace App\Services\VeltraxPay;

use App\Domain\Payments\Contracts\InboundPaymentsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VeltraxInboundProvider implements InboundPaymentsProvider
{
    public function __construct(private array $config = [])
    {
        $this->config = array_merge([
            'base_url'         => config('services.veltraxpay.base_url', 'https://api.veltraxpay.com'),
            'client_id'        => config('services.veltraxpay.client_id'),
            'client_secret'    => config('services.veltraxpay.client_secret'),
            'timeout'          => 20,
            'connect_timeout'  => 5,
            'verify'           => true,
            'deposit_endpoint' => '/api/payments/deposit',
            'login_endpoint'   => '/api/auth/login',
        ], $this->config);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    protected function http()
    {
        return Http::withOptions([
            'timeout'         => $this->config['timeout'],
            'connect_timeout' => $this->config['connect_timeout'],
            'verify'          => $this->config['verify'],
        ])->acceptJson()->asJson();
    }

    /**
     * 🔑 Autenticação e obtenção do token JWT
     */
    protected function authToken(): string
    {
        try {
            $res = $this->http()->post(
                $this->baseUrl() . ($this->config['login_endpoint'] ?? '/api/auth/login'),
                [
                    'client_id'     => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                ]
            );

            if ($res->failed()) {
                Log::warning('VeltraxPay login falhou', [
                    'status' => $res->status(),
                    'body'   => $res->body(),
                ]);
                $res->throw();
            }

            $token = $res->json('token') ?? $res->json('access_token');
            if (!$token) {
                throw new \RuntimeException('VeltraxPay: resposta de login sem token válido.');
            }

            return $token;

        } catch (ConnectionException $e) {
            throw new \RuntimeException('VeltraxPay: falha de conexão no login (' . $e->getMessage() . ').', 0, $e);
        } catch (RequestException $e) {
            $status = optional($e->response)->status();
            throw new \RuntimeException("VeltraxPay: erro HTTP no login (status {$status}): " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 💸 Criação de cobrança PIX (depósito)
     */
    public function createPixCharge(array $payload): array
    {
        $amount = (float) ($payload['amount'] ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('VeltraxPay: amount inválido.');
        }

        $externalId = (string) ($payload['external_id'] ?? '');
        if ($externalId === '') {
            throw new \InvalidArgumentException('VeltraxPay: external_id é obrigatório.');
        }

        $payer = $payload['payer'] ?? null;
        if (
            !is_array($payer)
            || empty($payer['name'])
            || empty($payer['email'])
            || empty($payer['document'])
            || empty($payer['phone'])
        ) {
            throw new \InvalidArgumentException('VeltraxPay: payer incompleto (name, email, document, phone são obrigatórios).');
        }

        $callback = (string) ($payload['clientCallbackUrl'] ?? '');
        if ($callback === '') {
            throw new \InvalidArgumentException('VeltraxPay: clientCallbackUrl é obrigatório.');
        }

        $body = [
            'amount'            => number_format($amount, 2, '.', ''),
            'external_id'       => $externalId,
            'clientCallbackUrl' => $callback,
            'payer'             => [
                'name'     => $payer['name'],
                'email'    => $payer['email'],
                'document' => $payer['document'],
                'phone'    => $payer['phone'],
            ],
        ];

        try {
            $token = $this->authToken();

            $res = $this->http()
                ->withToken($token)
                ->post($this->baseUrl() . ($this->config['deposit_endpoint'] ?? '/api/payments/deposit'), $body);

            if ($res->failed()) {
                Log::error('VeltraxPay depósito falhou', [
                    'status' => $res->status(),
                    'body'   => $res->body(),
                ]);
                $res->throw();
            }

            $data = $res->json();

            if (!isset($data['qrCodeResponse'])) {
                throw new \RuntimeException('VeltraxPay: resposta inválida — qrCodeResponse ausente.');
            }

            $qr = $data['qrCodeResponse'];

            return [
                'provider_transaction_id' => $qr['transactionId'] ?? null,
                'txid'                    => $qr['transactionId'] ?? null,
                'qr_code_text'            => $qr['qrcode'] ?? null,
                'qrcode_base64'           => null, // A API não retorna imagem
                'expires_at'              => null,
                'status'                  => strtolower($qr['status'] ?? 'pendente'),
                'raw'                     => $data,
            ];

        } catch (ConnectionException $e) {
            throw new \RuntimeException('VeltraxPay: falha de conexão na criação da cobrança (' . $e->getMessage() . ').', 0, $e);
        } catch (RequestException $e) {
            $code = optional($e->response)->status();
            $body = $e->response?->body();

            Log::error('VeltraxPay erro HTTP na cobrança', [
                'status'  => $code,
                'message' => $e->getMessage(),
                'body'    => $body,
            ]);

            throw new \RuntimeException("VeltraxPay: erro HTTP na cobrança (status {$code}): " . $e->getMessage(), 0, $e);
        }
    }
}
