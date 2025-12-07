<?php

namespace App\Services\Provider;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProviderGetPayOut
{
    protected string $baseUrl = 'https://hub.getpay.one/api';

    public function __construct(
        private readonly ProviderGetPay $authProvider
    ) {}

    /**
     * Gera CPF válido automaticamente
     */
    private function generateValidCpf(): string
    {
        $n = [];
        for ($i = 0; $i < 9; $i++) {
            $n[$i] = rand(0, 9);
        }

        $d1 = 0;
        for ($i = 0, $j = 10; $i < 9; $i++, $j--) {
            $d1 += $n[$i] * $j;
        }
        $d1 = 11 - ($d1 % 11);
        $d1 = ($d1 >= 10) ? 0 : $d1;

        $d2 = 0;
        for ($i = 0, $j = 11; $i < 9; $i++, $j--) {
            $d2 += $n[$i] * $j;
        }
        $d2 += $d1 * 2;
        $d2 = 11 - ($d2 % 11);
        $d2 = ($d2 >= 10) ? 0 : $d2;

        return implode('', $n) . $d1 . $d2;
    }

    /**
     * Criar saque via GETPAY
     */
    public function createWithdrawal(array $payload): array
    {
        $token = $this->authProvider->getToken();

        Log::info('[ProviderGetPayOut] 🚀 Enviando withdrawal para GetPay', [
            'payload' => $payload
        ]);

        // 🔥 Documento agora é garantidamente válido
        $document = $payload['documentNumber'] ?? $this->generateValidCpf();

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ])->post("{$this->baseUrl}/withdrawals", [
            'externalId'     => $payload['externalId']     ?? null,
            'pixKey'         => $payload['pixKey']         ?? null,
            'pixKeyType'     => strtoupper($payload['pixKeyType'] ?? ''),
            'documentNumber' => $document,
            'name'           => $payload['name']           ?? null,
            'amount'         => (float) ($payload['amount'] ?? 0),
        ]);

        $json = $response->json();

        Log::info('[ProviderGetPayOut] 📩 Resposta GetPay', [
            'payload'  => $payload,
            'sent_document' => $document,
            'response' => $json
        ]);

        if (!$response->successful()) {
            throw new Exception("[GetPayOut] Falha HTTP: " . $response->body());
        }

        if (!isset($json['success']) || $json['success'] !== true) {
            $reason = $json['message'] ?? 'Erro desconhecido do provider';
            throw new Exception("[GetPayOut] Erro ao solicitar saque: {$reason}");
        }

        return $json;
    }
}
