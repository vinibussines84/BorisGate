<?php

namespace App\Services\Cass;

use Illuminate\Support\Facades\Http;

class CassPagamentosService
{
    private string $baseUrl = 'https://api.casspagamentos.com/v1';

    public function __construct(
        private string $publicKey = '',
        private string $secretKey = ''
    ) {}

    private function authHeader(): string
    {
        $basic = base64_encode($this->publicKey . ':' . $this->secretKey);
        return 'Basic ' . $basic;
    }

    public function createPix(array $payload): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->authHeader(),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])->post("{$this->baseUrl}/transactions", $payload);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error'   => $response->json()['message'] ?? 'Erro desconhecido',
                    'raw'     => $response->json(),
                ];
            }

            return [
                'success' => true,
                'data'    => $response->json(),
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
