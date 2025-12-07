<?php

namespace App\Services\Withdraw;

use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWebhookWithdrawUpdatedJob;
use Carbon\Carbon;

class WithdrawService
{
    /**
     * Criar saque local + debitar saldo
     */
    public function create(User $user, float $gross, float $net, float $fee, array $payload): Withdraw
    {
        return DB::transaction(function () use ($user, $gross, $net, $fee, $payload) {

            $u = User::where('id', $user->id)->lockForUpdate()->first();

            if ($u->amount_available < $gross) {
                throw new \Exception('Saldo insuficiente.');
            }

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
                'provider'        => $payload['provider'] ?? 'unknown',
                'external_id'     => $payload['external_id'],
                'provider_reference' => null,
                'idempotency_key' => $payload['internal_ref'],
                'meta' => [
                    'internal_reference' => $payload['internal_ref'],
                    'refund_done'        => false,
                    'provider'           => $payload['provider'] ?? 'unknown',
                    'api_request'        => true,
                ],
            ]);
        });
    }

    /**
     * Falha → estorna imediatamente
     */
    public function refundLocal(Withdraw $withdraw, string $reason): void
    {
        Log::warning('💸 Refund LOCAL acionado', [
            'withdraw_id' => $withdraw->id,
            'reason'      => $reason,
        ]);

        DB::transaction(function () use ($withdraw, $reason) {

            $u = User::where('id', $withdraw->user_id)->lockForUpdate()->first();

            if (!($withdraw->meta['refund_done'] ?? false)) {
                $u->amount_available = round($u->amount_available + $withdraw->gross_amount, 2);
                $u->save();
            }

            $meta = $withdraw->meta ?? [];
            $meta['refund_done'] = true;
            $meta['error'] = $reason;
            $meta['failed_at'] = now();

            $withdraw->update([
                'status'       => 'failed',
                'meta'         => $meta,
                'processed_at' => now(),
            ]);
        });

        SendWebhookWithdrawUpdatedJob::dispatch(
            userId: $withdraw->user_id,
            withdrawId: $withdraw->id,
            status: 'FAILED',
            reference: (string) ($withdraw->provider_reference ?? $withdraw->external_id),
            raw: ['data' => ['description' => $reason]]
        )->onQueue('webhooks');

        Log::info('📤 Webhook OUT enviado (FAILED)', [
            'withdraw_id' => $withdraw->id,
            'reason'      => $reason,
        ]);
    }

    /**
     * Atualização após criação no provider
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
     * Marca como pago (AGORA COMPATÍVEL COM GETPAY)
     */
    public function markAsPaid(Withdraw $withdraw, array $payload, array $extra = [])
    {
        DB::transaction(function () use ($withdraw, $payload, $extra) {

            $processedAt = $extra['paid_at'] ?? now();
            $e2e = $extra['e2e'] ?? null;

            $meta = $withdraw->meta ?? [];

            $meta['paid_payload'] = $payload;
            $meta['getpay_e2e'] = $e2e;
            $meta['getpay_meta'] = $extra;
            $meta['paid_at'] = $processedAt;

            $withdraw->update([
                'status'       => 'paid',
                'processed_at' => $processedAt,
                'meta'         => $meta,
            ]);
        });

        SendWebhookWithdrawUpdatedJob::dispatch(
            $withdraw->user_id,
            $withdraw->id,
            'PAID',
            $withdraw->provider_reference, // AGORA CORRETO
            $payload
        )->onQueue('webhooks');

        Log::info('📤 Webhook OUT enviado (PAID)', [
            'withdraw_id' => $withdraw->id,
        ]);
    }
}
