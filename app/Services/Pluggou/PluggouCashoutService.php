<?php

namespace App\Services\Pluggou;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PluggouCashoutService
{
    protected string $baseUrl;
    protected string $publicKey;
    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl   = rtrim(config('services.pluggou.base_url', 'https://api.pluggoutech.com/api'), '/');
        $this->publicKey = config('services.pluggou.public_key');
        $this->secretKey = config('services.pluggou.secret_key');
    }

    /**
     * Cria um saque via Pluggou (Cashout PIX)
     *
     * @param  array{amount:int,key_type:string,key_value:string}  $payload
     * @return array
     */
    public function createCashout(array $payload): array
    {
        try {
            Log::info('[PLUGGOU CASHOUT] Payload enviado', $payload);

            // Validação básica antes do envio
            if (!isset($payload['amount'], $payload['key_type'], $payload['key_value'])) {
                throw new \InvalidArgumentException('Payload inválido: amount, key_type e key_value são obrigatórios.');
            }

            $response = Http::timeout(20)
                ->withHeaders([
                    'X-Public-Key' => $this->publicKey,
                    'X-Secret-Key' => $this->secretKey,
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/withdrawals", [
                    'amount'    => $payload['amount'],
                    'key_type'  => $payload['key_type'],
                    'key_value' => $payload['key_value'],
                ]);

            $data = $response->json() ?? [];

            Log::info('[PLUGGOU CASHOUT] Resposta recebida', [
                'status'   => $response->status(),
                'response' => $data,
            ]);

            // Se resposta HTTP não for sucesso (>=400)
            if (!$response->successful()) {
                return [
                    'success'  => false,
                    'message'  => $data['message'] ?? 'Erro de comunicação com Pluggou',
                    'response' => $data,
                ];
            }

            // Normaliza saída
            return [
                'success' => (bool) ($data['success'] ?? false),
                'message' => $data['message'] ?? 'Saque processado',
                'data'    => $data['data'] ?? [],
            ];

        } catch (\Throwable $e) {
            Log::error('[PLUGGOU CASHOUT] Falha ao criar saque', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success'   => false,
                'message'   => 'Exceção durante comunicação com Pluggou',
                'exception' => $e->getMessage(),
            ];
        }
    }
}
