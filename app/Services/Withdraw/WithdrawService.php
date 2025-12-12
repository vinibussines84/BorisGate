<?php

namespace App\Services\Withdraw;

use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWebhookWithdrawUpdatedJob;
use InvalidArgumentException;

class WithdrawService
{
    /**
     * Criar saque local + debitar saldo
     * DOMÍNIO → usa SEMPRE key / key_type
     */
    public function create(
        User $user,
        float $gross,
        float $net,
        float $fee,
        array $payload
    ): Withdraw {

        /**
         * 🔐 Blindagem do domínio
         */
        foreach (['key', 'key_type', 'external_id', 'internal_ref'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new InvalidArgumentException(
                    "Payload inválido para criação de saque. Campo ausente: {$field}"
                );
            }
        }

        return DB::transaction(function () use ($user, $gross, $net, $fee, $payload) {

            /**
             * 🔒 Lock de saldo
             */
            $u = User::where('id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($u->amount_available < $gross) {
                throw new \Exception('Saldo insuficiente.');
            }

            /**
             * 💸 Debita saldo
             */
            $u->amount_available = round(
                $u->amount_available - $gross,
                2
            );
            $u->save();

            /**
             * 🧾 Cria saque local
             */
            return Withdraw::create([
                'user_id'      => $u->id,
                'amount'       => $net,
                'gross_amount' => $gross,
                'fee_amount'   => $fee,

                // ✅ DOMÍNIO PADRÃO
                'pixkey'      => $payload['key'],
                'pixkey_type' => strtolower($payload['key_type']),

                'status'   => Withdraw::STATUS_PENDING,
                'provider' => $payload['provider'] ?? 'xflow',

                'external_id'        => $payload['external_id'],
                'provider_reference' => null,

                // ✅ idempotência única e rastreável
                'idempotency_key' => $payload['internal_ref'],

                'meta' => [
                    'internal_reference' => $payload['internal_ref'],
                    'refund_done'        => false,
                    'provider'           => $payload['provider'] ?? 'xflow',
                    'api_request'        => true,
                ],
            ]);
        });
    }

    /**
     * ❌ Falha → estorno imediato
     */
    public function refundLocal(Withdraw $withdraw, string $reason): void
    {
        Log::warning('💸 Refund LOCAL acionado', [
            'withdraw_id' => $withdraw->id,
            'reason'      => $reason,
        ]);

        DB::transaction(function () use ($withdraw, $reason) {

            $u = User::where('id', $withdraw->user_id)
                ->lockForUpdate()
                ->first();

            /**
             * 🔁 Evita estorno duplicado
             */
            if (!($withdraw->meta['refund_done'] ?? false)) {
                $u->amount_available = round(
                    $u->amount_available + $withdraw->gross_amount,
                    2
                );
                $u->save();
            }

            $meta = $withdraw->meta ?? [];

            $meta['refund_done'] = true;
            $meta['error']       = $reason;
            $meta['failed_at']   = now();

            $withdraw->update([
                'status'       => Withdraw::STATUS_FAILED,
                'meta'         => $meta,
                'processed_at' => now(),
            ]);
        });

        /**
         * 📤 Webhook FAILED
         */
        SendWebhookWithdrawUpdatedJob::dispatch(
            userId: $withdraw->user_id,
            withdrawId: $withdraw->id,
            status: 'FAILED',
            reference: (string) (
                $withdraw->provider_reference
                ?? $withdraw->external_id
            ),
            raw: [
                'data' => [
                    'description' => $reason,
                ],
            ]
        )->onQueue('webhooks');

        Log::info('📤 Webhook OUT enviado (FAILED)', [
            'withdraw_id' => $withdraw->id,
        ]);
    }

    /**
     * 🔁 Atualiza referência do provider
     */
    public function updateProviderReference(
        Withdraw $withdraw,
        string $providerRef,
        string $status,
        array $providerPayload
    ): void {
        DB::transaction(function () use (
            $withdraw,
            $providerRef,
            $status,
            $providerPayload
        ) {
            $withdraw->update([
                'provider_reference' => $providerRef,
                'status'             => $status,
                'meta'               => array_merge(
                    $withdraw->meta ?? [],
                    [
                        'provider_initial_response' => $providerPayload,
                    ]
                ),
            ]);
        });
    }

    /**
     * ✅ Marca saque como pago
     */
    public function markAsPaid(
        Withdraw $withdraw,
        array $payload,
        array $extra = []
    ): void {
        DB::transaction(function () use ($withdraw, $payload, $extra) {

            $processedAt = $extra['paid_at'] ?? now();
            $meta = $withdraw->meta ?? [];

            /**
             * 🧹 Limpa lixo de falha
             */
            unset(
                $meta['error'],
                $meta['failed_at'],
                $meta['refund_done'],
                $meta['refused_reason']
            );

            $meta['paid_payload'] = $payload;
            $meta['paid_at']      = $processedAt;

            if (!empty($extra)) {
                $meta['provider_webhook'] = $extra;
            }

            $withdraw->update([
                'status'       => Withdraw::STATUS_PAID,
                'processed_at' => $processedAt,
                'meta'         => $meta,
            ]);
        });

        /**
         * 📤 Webhook PAID
         */
        SendWebhookWithdrawUpdatedJob::dispatch(
            $withdraw->user_id,
            $withdraw->id,
            'PAID',
            $withdraw->provider_reference,
            $payload
        )->onQueue('webhooks');

        Log::info('📤 Webhook OUT enviado (PAID)', [
            'withdraw_id' => $withdraw->id,
        ]);
    }
}
