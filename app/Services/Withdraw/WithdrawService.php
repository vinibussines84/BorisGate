<?php

namespace App\Services\Withdraw;

use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWebhookWithdrawUpdatedJob;

class WithdrawService
{
    /**
     * Criar saque local + debitar saldo (BRUTO 1 vez)
     */
    public function create(User $user, float $gross, float $net, float $fee, array $payload): Withdraw
    {
        return DB::transaction(function () use ($user, $gross, $net, $fee, $payload) {

            // trava saldo
            $u = User::where('id', $user->id)->lockForUpdate()->first();

            if ($u->amount_available < $gross) {
                throw new \Exception('Saldo insuficiente.');
            }

            // 🔥 debita APENAS AQUI
            $u->amount_available = round($u->amount_available - $gross, 2);
            $u->save();

            return Withdraw::create([
                'user_id'         => $u->id,
                'amount'          => $net,
                'gross_amount'    => $gross,
                'fee_amount'      => $fee,
                'pixkey'          => $payload['key'],
                'pixkey_type'     => $payload['key_type'],
                'status'          => 'processing',
                'provider'        => 'pluggou',
                'external_id'     => $payload['external_id'],
                'provider_reference' => null,
                'idempotency_key' => $payload['internal_ref'],
                'meta' => [
                    'internal_reference' => $payload['internal_ref'],
                    'refund_done'        => false,
                    'provider'           => 'pluggou',
                    'api_request'        => true,
                ],
            ]);
        });
    }

    /**
     * 🔥 Falhou — ESTORNA IMEDIATAMENTE (independente de motivo)
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

            // evita estornar mais de 1 vez
            if (!($withdraw->meta['refund_done'] ?? false)) {
                $u->amount_available = round($u->amount_available + $withdraw->gross_amount, 2);
                $u->save();
            }

            // atualiza meta
            $meta = $withdraw->meta ?? [];
            $meta['refund_done'] = true;
            $meta['error'] = $reason;
            $meta['failed_at'] = now();

            $withdraw->update([
                'status' => 'failed',
                'meta'   => $meta,
                'processed_at' => now(),
            ]);
        });

        // 🔥 webhook OUT (FAILED)
        SendWebhookWithdrawUpdatedJob::dispatch(
            userId: $withdraw->user_id,
            withdrawId: $withdraw->id,
            status: 'FAILED',
            reference: (string) ($withdraw->provider_reference ?? $withdraw->meta['internal_reference'] ?? $withdraw->id),
            raw: [
                'data' => [
                    'description' => $reason
                ]
            ]
        )->onQueue('webhooks');

        Log::info('📤 Webhook OUT enviado (FAILED)', [
            'withdraw_id' => $withdraw->id,
            'reason'      => $reason,
        ]);
    }

    /**
     * 🔥 Atualização após criação no provider
     */
    public function updateProviderReference(Withdraw $withdraw, string $providerRef, string $status, array $providerPayload)
    {
        DB::transaction(function () use ($withdraw, $providerRef, $status, $providerPayload) {

            $withdraw->update([
                'provider_reference' => $providerRef,
                'status'             => $status,
                'meta' => array_merge($withdraw->meta ?? [], [
                    'provider_initial_response' => $providerPayload,
                ]),
            ]);
        });
    }

    /**
     * 🔥 Falhou via webhook PodPay (não é usado para Pluggou, mas mantido)
     */
    public function refundWebhookFailed(Withdraw $withdraw, array $payload)
    {
        Log::error('❌ Saque FAILED via webhook', ['withdraw_id' => $withdraw->id]);

        DB::transaction(function () use ($withdraw, $payload) {

            $u = User::where('id', $withdraw->user_id)->lockForUpdate()->first();

            if (!($withdraw->meta['refund_done'] ?? false)) {
                $u->amount_available = round($u->amount_available + $withdraw->gross_amount, 2);
                $u->save();
            }

            $meta = $withdraw->meta ?? [];
            $meta['refund_done'] = true;
            $meta['webhook_failed_payload'] = $payload;
            $meta['failed_at'] = now();

            $withdraw->update([
                'status' => 'failed',
                'meta'   => $meta,
                'processed_at' => now(),
            ]);
        });

        SendWebhookWithdrawUpdatedJob::dispatch(
            userId: $withdraw->user_id,
            withdrawId: $withdraw->id,
            status: 'FAILED',
            reference: (string) ($withdraw->meta['internal_reference'] ?? $withdraw->id),
            raw: $payload
        )->onQueue('webhooks');

        Log::info('📤 Webhook OUT enviado (FAILED via webhook)');
    }

    /**
     * 🔥 Marca como pago
     */
    public function markAsPaid(Withdraw $withdraw, array $payload)
    {
        DB::transaction(function () use ($withdraw, $payload) {

            $meta = $withdraw->meta ?? [];
            $meta['paid_payload'] = $payload;
            $meta['paid_at'] = now();

            $withdraw->update([
                'status'       => 'paid',
                'processed_at' => now(),
                'meta'         => $meta,
            ]);
        });

        SendWebhookWithdrawUpdatedJob::dispatch(
            userId: $withdraw->user_id,
            withdrawId: $withdraw->id,
            status: 'PAID',
            reference: (string) ($withdraw->meta['internal_reference'] ?? $withdraw->id),
            raw: $payload
        )->onQueue('webhooks');

        Log::info('📤 Webhook OUT enviado (PAID)');
    }
}
