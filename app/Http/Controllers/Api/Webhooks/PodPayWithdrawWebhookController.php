<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PodPayWithdrawWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        try {

            $payload = $request->json()->all();

            Log::info('📩 Webhook PodPay recebido', ['payload' => $payload]);

            /* ============================================================
             * 1️⃣ Extrair dados essenciais
             * ============================================================ */
            $status      = strtoupper(data_get($payload, 'data.status', 'UNKNOWN'));
            $description = strtolower((string) data_get($payload, 'data.description', ''));
            $historyMsg  = strtolower((string) data_get($payload, 'data.history.0.message', ''));

            /* ============================================================
             * 2️⃣ Referência = provider_reference
             * ============================================================ */
            $reference = (string) data_get($payload, 'objectId');

            if (!$reference) {
                return response()->json(['error' => 'missing_reference'], 422);
            }

            /* ============================================================
             * 3️⃣ Localizar o saque
             * ============================================================ */
            $withdraw = Withdraw::where('provider_reference', $reference)->first();

            if (!$withdraw) {
                Log::warning('⚠️ PodPay webhook: withdraw não encontrado', [
                    'reference' => $reference
                ]);
                return response()->json(['ignored' => true]);
            }

            $user = User::find($withdraw->user_id);

            /* ============================================================
             * 4️⃣ Idempotência
             * ============================================================ */
            if (in_array($withdraw->status, ['paid', 'failed'])) {
                Log::info('ℹ️ Webhook PodPay ignorado (já finalizado).', [
                    'withdraw_id' => $withdraw->id,
                    'status'      => $withdraw->status,
                ]);
                return response()->json(['ignored' => true]);
            }

            /* ============================================================
             * 5️⃣ Regras de status
             * ============================================================ */
            $isCompleted = $status === 'COMPLETED';

            $isFailed =
                str_starts_with($description, 'failed') ||    // description: FAILED: ...
                str_contains($historyMsg, 'não encontramos') || // chave inexistente
                in_array($status, ['FAILED', 'ERROR', 'CANCELED']);

            $isProcessing = $status === 'PROCESSING' && !$isFailed;

            /* ============================================================
             * 6️⃣ Status intermediário → ignorar
             * ============================================================ */
            if ($isProcessing) {
                Log::info('⏸ Webhook PodPay ignorado (PROCESSING).');
                return response()->json(['ignored' => true]);
            }

            /* ============================================================
             * 7️⃣ CASO DE FALHA → estornar saldo + marcar failed
             * ============================================================ */
            if ($isFailed) {

                DB::transaction(function () use ($withdraw, $user, $payload) {

                    $u = User::where('id', $user->id)->lockForUpdate()->first();
                    $u->amount_available += $withdraw->gross_amount;
                    $u->save();

                    $meta = $withdraw->meta ?? [];
                    $meta['podpay_failed_payload'] = $payload;
                    $meta['failed_at'] = now();

                    $withdraw->update([
                        'status' => 'failed',
                        'meta'   => $meta,
                    ]);
                });

                $this->notifyClient($user, $withdraw, null, 'withdraw.failed');

                Log::error('❌ Saque PodPay marcado como FAILED', [
                    'withdraw_id' => $withdraw->id,
                    'reason'      => $description ?: $historyMsg,
                ]);

                return response()->json(['success' => true, 'status' => 'failed']);
            }

            /* ============================================================
             * 8️⃣ CASO DE SUCESSO → COMPLETED = pago
             * ============================================================ */
            if ($isCompleted) {

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

                Log::info('✅ Saque PodPay marcado como PAGO', [
                    'withdraw_id' => $withdraw->id,
                    'reference'   => $reference,
                    'e2e'         => $e2e,
                ]);

                // Enviar webhook OUT ao cliente com E2E
                $this->notifyClient($user, $withdraw, $e2e, 'withdraw.paid');

                return response()->json(['success' => true, 'status' => 'paid']);
            }

            /* ============================================================
             * 9️⃣ Status desconhecido → ignora
             * ============================================================ */
            Log::warning('⚠️ Webhook PodPay status desconhecido', ['status' => $status]);

            return response()->json(['ignored' => true]);

        } catch (\Throwable $e) {

            Log::error('🚨 Erro no Webhook PodPay', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }

    /**
     * 🔢 Gerar E2E PIX (SOMENTE PARA SAQUES PAGOS)
     */
    private function generatePixE2E(Withdraw $withdraw): string
    {
        $ispb = '90400888'; // ISPB igual ao exemplo fornecido
        $timestamp = now()->format('YmdHis');
        $seq = str_pad($withdraw->id, 9, '0', STR_PAD_LEFT);

        return "E{$ispb}{$timestamp}{$seq}";
    }

    /**
     * 📤 Enviar webhook OUT ao cliente
     */
    private function notifyClient(?User $user, Withdraw $withdraw, $e2e, string $event)
    {
        if (!$user?->webhook_enabled || !$user?->webhook_out_url) {
            return;
        }

        $payload = [
            'withdraw_id' => $withdraw->id,
            'external_id' => $withdraw->external_id,
            'amount'      => $withdraw->gross_amount,
            'liquid'      => $withdraw->amount,
            'status'      => $withdraw->status,
            'reference'   => $withdraw->provider_reference,
        ];

        // 🔥 E2E só enviado se FOR SAQUE PAGO
        if ($withdraw->status === 'paid') {
            $payload['e2e']     = $e2e;
            $payload['paid_at'] = $withdraw->meta['paid_at'] ?? null;
        }

        try {
            Http::timeout(10)->post($user->webhook_out_url, [
                'event' => $event,
                'data'  => $payload,
            ]);

            Log::info('📤 Webhook OUT enviado ao cliente', [
                'event'       => $event,
                'withdraw_id' => $withdraw->id,
            ]);

        } catch (\Throwable $e) {
            Log::warning('⚠️ Falha ao enviar webhook OUT ao cliente', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
