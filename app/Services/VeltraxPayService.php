<?php

namespace App\Services;

use App\Models\Withdraw;
use App\Services\TrustPay\TrustPayLegacyOutboundProvider;
use Illuminate\Support\Str;

class VeltraxPayService
{
    public function __construct(
        protected TrustPayLegacyOutboundProvider $trustOut // injetado pelo container
    ) {}

    /**
     * Método já usado no controller – envia o LÍQUIDO.
     * Mantém a assinatura, mas por baixo chama TrustPay.
     */
    public function createWithdrawalNet(Withdraw $withdraw, string $externalId, ?string $idempotencyKey = null): array
    {
        return $this->createWithdrawal($withdraw, $externalId, $idempotencyKey, [
            'amountOverride' => (float) $withdraw->amount, // líquido
        ]);
    }

    /**
     * Fallback genérico – lê options['amountOverride'] se existir.
     */
    public function createWithdrawal(Withdraw $withdraw, string $externalId, ?string $idempotencyKey = null, array $options = []): array
    {
        $amount = isset($options['amountOverride'])
            ? (float) $options['amountOverride']
            : (float) $withdraw->amount; // por padrão, líquido

        // Normaliza pix type (controller já valida)
        $pixType = strtolower((string) $withdraw->pixkey_type);
        $doc     = (string) data_get($withdraw->meta ?? [], 'recipient_document', ''); // se você armazenar no meta
        if ($doc === '') {
            // fallback: usa documento do próprio usuário, se fizer sentido no seu domínio
            $doc = (string) preg_replace('/\D+/', '', (string) ($withdraw->user?->document ?? ''));
        }

        $name = (string) data_get($withdraw->meta ?? [], 'recipient_name', $withdraw->user?->name ?? 'Beneficiário');

        // Monta payload esperado pelo provider de saída
        $payload = [
            'external_id'     => $externalId ?: (string) Str::ulid(),
            'pixkey'          => $withdraw->pixkey,
            'pixkey_type'     => $pixType,                 // email|cpf|cnpj|phone|evp
            'document_number' => $doc,                     // só dígitos
            'name'            => $name,
            'amount'          => $amount,                  // líquido
        ];

        $resp = $this->trustOut->createPixWithdrawal($payload);

        // Normaliza para o controller
        return [
            'provider'     => 'trustpay',
            'provider_ref' => $resp['provider_withdrawal_id'] ?? null,
            'status'       => $resp['status'] ?? 'pending',
            'amount'       => $resp['amount'] ?? number_format($amount, 2, '.', ''),
            'message'      => 'OK',
        ];
    }
}
