<?php

namespace App\Services\Provider;

class ProviderService
{
    protected $provider;

    public function __construct()
    {
        // Aqui você define qual provider está ativo
        // Amanhã você pode trocar para outro sem mudar sua lógica
        $this->provider = new ProviderCoffePay();
    }

    /**
     * Criar transação PIX (cash-in)
     */
    public function createPix(float $amount, array $payer)
    {
        return $this->provider->createPix($amount, $payer);
    }

    /**
     * Consultar status de transação
     */
    public function getTransactionStatus(string $transactionId)
    {
        return $this->provider->getTransactionStatus($transactionId);
    }

    /**
     * Criar pedido de saque (cash-out)
     */
    public function withdraw(float $amount, array $recipient)
    {
        return $this->provider->withdraw($amount, $recipient);
    }

    /**
     * Validação do webhook (opcional)
     */
    public function processWebhook(array $payload)
    {
        return $this->provider->processWebhook($payload);
    }
}
