<?php

namespace App\Services\ReflowPay;

class ReflowPayCashoutService
{
    protected ?string $baseUrl = null;
    protected ?string $apiKey = null;

    public function __construct()
    {
        // Desativado temporariamente
        $this->baseUrl = null;
        $this->apiKey  = null;
    }

    public function createCashout(array $payload)
    {
        // Em vez de chamar a API, retorna uma resposta simulada
        return [
            'success' => false,
            'message' => 'Serviço ReflowPayCashoutService temporariamente desativado.',
            'payload' => $payload,
        ];
    }
}
