<?php

namespace App\Domain\Payments\Contracts;

interface InboundPaymentsProvider
{
    /**
     * Cria uma cobrança PIX (cash-in).
     * Deve retornar:
     * - provider_transaction_id
     * - txid
     * - qr_code_text  (copia/cola)
     * - qrcode_base64 (opcional)
     * - expires_at    (opcional, ISO8601)
     * - raw           (payload bruto do provedor)
     */
    public function createPixCharge(array $payload): array;
}
