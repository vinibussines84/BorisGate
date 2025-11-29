<?php

namespace App\Services\Pix;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StaticQrGenerator
{
    /**
     * Chama a API Stric para gerar QR Code PIX estático.
     *
     * @param  string $accountId ID da conta Stric (session('stric_account_id'))
     * @param  string $token     Token Bearer ativo (session('rpnet_token'))
     * @param  string $tenantId  ID do tenant (config('services.stric.tenant_id'))
     * @param  array  $payload   [ key => string, amount => float|null, description => string|null ]
     * @return array             [ 'image' => base64|null, 'text' => string|null ]
     * @throws \RuntimeException Com código HTTP da upstream em $e->getCode()
     */
    public function generate(string $accountId, string $token, string $tenantId, array $payload): array
    {
        $url = "https://global.stric.com.br/accounts/{$accountId}/pix/qrcode/static";
        $cid = (string) Str::uuid();

        // Sanitiza o payload final: remove chaves vazias e garante tipo numérico de amount
        $body = [
            'key'         => isset($payload['key']) && $payload['key'] !== '' ? (string) $payload['key'] : null,
            'amount'      => array_key_exists('amount', $payload) && $payload['amount'] !== '' && $payload['amount'] !== null
                ? (float) $payload['amount']
                : null,
            'description' => isset($payload['description']) && $payload['description'] !== '' ? (string) $payload['description'] : null,
        ];
        // remove nulls (preserva 0.0)
        $body = array_filter($body, fn ($v) => $v !== null);

        Log::info('🚀 Enviando requisição para Stric QRCode estático', [
            'cid'     => $cid,
            'url'     => $url,
            'payload' => $body,
        ]);

        $response = Http::withHeaders([
                'Authorization'    => "Bearer {$token}",
                'x-tenant-id'      => $tenantId,
                'X-Correlation-ID' => $cid,
            ])
            ->acceptJson()
            ->asJson()                 // força JSON com Content-Type correto
            ->timeout(15)
            ->retry(
                3,                     // total de tentativas (1 + 2 retries)
                250,                   // backoff base (ms)
                function ($exception, Response $response = null) {
                    if ($exception instanceof ConnectionException) {
                        return true;   // falha de rede -> retry
                    }
                    if ($response) {
                        return in_array($response->status(), [429, 500, 502, 503, 504], true);
                    }
                    return false;
                },
                throw: false
            )
            ->post($url, $body);

        Log::info('📡 Resposta Stric QRCode estático', [
            'cid'        => $cid,
            'status'     => $response->status(),
            'retryAfter' => $response->header('Retry-After'),
        ]);

        if ($response->failed()) {
            // Tenta extrair mensagem legível
            $json = [];
            try { $json = $response->json(); } catch (\Throwable $e) {}
            $code    = $response->status() ?: 502;
            $message = $json['message'] ?? ($json['error'] ?? $response->body());

            Log::error('❌ Falha na Stric QRCode estático', [
                'cid'    => $cid,
                'status' => $code,
                'body'   => $json ?: $response->body(),
            ]);

            // Lança com o código HTTP da upstream para o controller tratar
            throw new \RuntimeException(
                is_string($message) ? $message : 'Erro ao gerar QR Code na Stric.',
                $code
            );
        }

        $data = $response->json();

        return [
            'image' => $data['qrCode']['image'] ?? null,
            'text'  => $data['qrCode']['text']  ?? null,
        ];
    }
}
