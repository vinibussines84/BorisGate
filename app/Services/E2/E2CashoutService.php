<?php

namespace App\Services\E2;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class E2CashoutService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.e2.base_url');
        $this->token   = config('services.e2.jwt');
    }

    /**
     * Enviar solicitação de saque para a E2.
     */
    public function createWithdraw(array $payload)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type'  => 'application/json',
            ])->post("{$this->baseUrl}/api/withdrawals", $payload);

            $json = $response->json();

            Log::info('[E2CashoutService] Resposta E2', [
                'payload'   => $payload,
                'response'  => $json
            ]);

            return $json;

        } catch (\Throwable $e) {

            Log::error('[E2CashoutService] Erro ao chamar E2', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
