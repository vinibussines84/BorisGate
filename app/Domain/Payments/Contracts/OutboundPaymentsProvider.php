<?php

namespace App\Domain\Payments\Contracts;

interface OutboundPaymentsProvider
{
    /**
     * Cria uma solicitação de saque PIX no provedor.
     *
     * Parâmetros esperados no $payload (padrão):
     * - external_id      (string)  Identificador único da sua aplicação
     * - pixkey           (string)  Chave PIX destino
     * - pixkey_type      (string)  cpf|cnpj|email|phone|evp (normalizado no provider)
     * - document_number  (string)  Documento do recebedor (somente dígitos)
     * - name             (string)  Nome completo do recebedor
     * - amount           (float)   Valor do saque (usar líquido, se sua regra for essa)
     *
     * Retorno mínimo:
     * - provider_withdrawal_id (string)  ID no provedor
     * - external_id            (string)
     * - amount                 (string|number)
     * - status                 (pending|processing|paid|failed|canceled)
     * - created_at             (string|null) ISO8601 ou formato original
     * - raw                    (array)       payload bruto da resposta
     */
    public function createPixWithdrawal(array $payload): array;
}