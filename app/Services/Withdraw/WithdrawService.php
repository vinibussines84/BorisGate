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
                'status'          => 'pending',
                'provider'        => 'podpay',
                'external_id'     => $payload['external_id'],
                'idempotency_key' => $payload['internal_ref'],
                'meta' => [
                    'internal_reference' => $payload['internal_ref'],
                    'tax_fixed'   => $user->tax_out_fixed,
                    'tax_percent' => $user->tax_out_percent,
                    'api_request' => true,
                ],
            ]);
        });
    }

    /**
     * Se falhar ANTES da criação no provider: estorna o BRUTO e marca como failed
     */
    public function refundLocal(Withdraw $withdraw, string $reason): void
    {
        Log::warning('💸 Refund LOCAL', [
            'withdraw_id' => $withdraw->id,
            'reason'      => $reason,
        ]);

        DB::transaction(function () use ($withdraw, $reason) {
            $u = User::where('id', $withdraw->user_id)->lockForUpdate()->first();

            $u->amount_available = round($u->amount_available + $withdraw->gross_amount, 2);
            $u->save();

            $meta = $withdraw->meta ?? [];
            $meta['error'] = $reason;
            $meta['refund_done'] = true;

            $withdraw->update([
                'status' => 'failed',
                'meta'   => $meta,
            ]);
        });
    }

    /**
     * Atualiza saque após sucesso na criação do provider
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
     * Processa o webhook da PodPay e decide se estorna ou confirma
     */
    public function handleWebhook(array $payload): array
    {
        $providerRef = (string) data_get($payload, 'objectId');
        $status      = strtoupper((string) data_get($payload, 'data.status', 'UNKNOWN'));
        $desc        = (string) data_get($payload, 'data.description', '');

        $withdraw = Withdraw::where('provider_reference', $providerRef)->first();

        if (!$withdraw) {
            Log::warning('⚠️ Webhook ignorado — saque não encontrado.', [
                'provider_reference' => $providerRef,
                'status' => $status,
            ]);
            return ['ignored' => true];
        }

        if (in_array($withdraw->status, ['failed', 'paid'])) {
            return ['ignored' => true];
        }

        $descLower = strtolower($desc);

        $isFailed = collect([
            'FAILED', 'ERROR', 'CANCELED', 'CANCELLED', 'REJECTED', 'REFUSED', 'DENIED', 'DECLINED'
        ])->contains($status)
            || str_contains($descLower, 'fail')
            || str_contains($descLower, 'error')
            || str_contains($descLower, 'cancel')
            || str_contains($descLower, 'reject')
            || str_contains($descLower, 'refuse')
            || str_contains($descLower, 'denied')
            || str_contains($descLower, 'declined');

        $isCompleted = ($status === 'COMPLETED');

        Log::info('🔎 Webhook PodPay recebido', [
            'withdraw_id' => $withdraw->id,
            'status' => $status,
            'desc' => $desc,
            'isFailed' => $isFailed,
            'isCompleted' => $isCompleted,
        ]);

        if ($isFailed) {
            $this->refundWebhookFailed($withdraw, $payload);
            return ['failed' => true];
        }

        if ($isCompleted) {
            $this->markAsPaid($withdraw, $payload);
            return ['paid' => true];
        }

        return ['ignored' => true];
    }

    /**
     * Estorna via webhook — estorna o BRUTO e marca como FAILED + dispara webhook OUT
     */
    private function refundWebhookFailed(Withdraw $withdraw, array $payload)
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
            ]);
        });

        // 🚀 Dispara webhook OUT (withdraw.updated - FAILED)
        dispatch(new SendWebhookWithdrawUpdatedJob(
            userId: $withdraw->user_id,
            withdrawId: $withdraw->id,
            status: 'FAILED',
            reference: (string) ($withdraw->meta['internal_reference'] ?? $withdraw->id),
            raw: $payload
        ))->onQueue('webhooks');

        Log::info('📤 Webhook OUT disparado (withdraw.updated FAILED)', [
            'withdraw_id' => $withdraw->id,
            'user_id' => $withdraw->user_id,
        ]);
    }

    /**
     * Marca como PAGO e dispara webhook OUT (withdraw.updated - APPROVED)
     */
    private function markAsPaid(Withdraw $withdraw, array $payload)
    {
        DB::transaction(function () use ($withdraw, $payload) {
            $e2e = $this->generatePixE2E($withdraw);

            $meta = $withdraw->meta ?? [];
            $meta['podpay_success_payload'] = $payload;
            $meta['e2e'] = $e2e;
            $meta['paid_at'] = now();

            $withdraw->update([
                'status'       => 'paid',
                'processed_at' => now(),
                'meta'         => $meta,
            ]);
        });

        // 🚀 Dispara webhook OUT (withdraw.updated - APPROVED)
        dispatch(new SendWebhookWithdrawUpdatedJob(
            userId: $withdraw->user_id,
            withdrawId: $withdraw->id,
            status: 'APPROVED',
            reference: (string) ($withdraw->meta['internal_reference'] ?? $withdraw->id),
            raw: $payload
        ))->onQueue('webhooks');

        Log::info('📤 Webhook OUT disparado (withdraw.updated APPROVED)', [
            'withdraw_id' => $withdraw->id,
            'user_id'     => $withdraw->user_id,
        ]);
    }

    /**
     * 🔢 Gera E2E PIX
     */
    private function generatePixE2E(Withdraw $withdraw): string
    {
        $ispb = '90400888';
        $timestamp = now()->format('YmdHis');
        $seq = str_pad($withdraw->id, 9, '0', STR_PAD_LEFT);
        return "E{$ispb}{$timestamp}{$seq}";
    }
}
