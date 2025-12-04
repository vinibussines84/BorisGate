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
        $this->baseUrl   = config('services.pluggou.base_url', 'https://api.pluggoutech.com/api');
        $this->publicKey = config('services.pluggou.public_key');
        $this->secretKey = config('services.pluggou.secret_key');
    }

    /**
     * Cria um saque via Pluggou (em centavos)
     */
    public function createCashout(array $payload): array
    {
        try {
            Log::info('[PLUGGOU CASHOUT] Payload enviado', $payload);

            $response = Http::timeout(20)
                ->withHeaders([
                    'X-Public-Key' => $this->publicKey,
                    'X-Secret-Key' => $this->secretKey,
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/withdrawals", $payload);

            $data = $response->json();
            Log::info('[PLUGGOU CASHOUT] Resposta recebida', $data);

            if (!$response->successful()) {
                throw new \RuntimeException($data['message'] ?? 'Erro ao comunicar com Pluggou');
            }

            return $data;

        } catch (\Throwable $e) {
            Log::error('[PLUGGOU CASHOUT] Falha ao criar saque', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
