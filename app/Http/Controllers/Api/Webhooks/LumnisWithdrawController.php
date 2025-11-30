<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LumnisWithdrawController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $payload = $request->json()->all();

            Log::info('↪️ Webhook Lumnis recebido', ['payload' => $payload]);

            /* ============================================================
             * 1️⃣ STATUS
             * ============================================================ */
            $status = strtolower(data_get($payload, 'status', 'unknown'));

            // status possíveis: pending, approved, paid, error, failed
            $isFinalSuccess = in_array($status, ['approved', 'paid']);
            $isError        = in_array($status, ['error', 'failed']);

            /* ============================================================
             * 2️⃣ PEGAR O IDENTIFICADOR
             * ============================================================ */
            $reference =
                   data_get($payload, 'id')
                ?? data_get($payload, 'receipt.0.identifier')
                ?? null;

            if (!$reference) {
                Log::warning('⚠️ Webhook Lumnis: referência ausente', ['payload' => $payload]);
                return response()->json(['success' => false, 'error' => 'missing_reference']);
            }

            /* ============================================================
             * 3️⃣ BUSCAR O SAQUE LOCAL
             * ============================================================ */
            $withdraw = Withdraw::where('provider_reference', $reference)
                ->orWhere('external_id', $reference)
                ->first();

            if (!$withdraw) {
                Log::warning('❌ Webhook Lumnis: saque não encontrado', [
                    'reference' => $reference,
                    'payload'   => $payload,
                ]);
                return response()->json(['success' => false, 'error' => 'withdraw_not_found']);
            }

            $user = User::find($withdraw->user_id);

            /* ============================================================
             * 4️⃣ IDEMPOTÊNCIA
             * ============================================================ */
            if (in_array($withdraw->status, ['paid', 'failed'])) {
                Log::info('ℹ️ Webhook ignorado (já processado)', [
                    'withdraw_id' => $withdraw->id,
                    'reference'   => $reference,
                ]);
                return response()->json(['success' => true, 'ignored' => true]);
            }

            /* ============================================================
             * 5️⃣ NORMALIZAÇÃO DE VALORES + META
             * ============================================================ */
            $receipt = (array) data_get($payload, 'receipt.0', []);

            $requestedReais = ((int) data_get($payload, 'requested', 0)) / 100;
            $paidReais      = ((int) data_get($payload, 'paid', 0)) / 100;

            $meta = (array) $withdraw->meta;
            $meta['raw_provider_payload'] = $payload;
            $meta['paid_at']        = now()->toIso8601String();
            $meta['refused_reason'] = data_get($receipt, 'refused_reason');
            $meta['endtoend']       = data_get($receipt, 'endtoend');
            $meta['receiver_name']  = data_get($receipt, 'receiver_name');
            $meta['receiver_bank']  = data_get($receipt, 'receiver_bank');
            $meta['receiver_ispb']  = data_get($receipt, 'receiver_bank_ispb');

            /* ============================================================
             * 6️⃣ CASO DE ERRO → ESTORNAR E MARCAR COMO FALHO
             * ============================================================ */
            if ($isError) {
                DB::transaction(function () use ($user, $withdraw, $meta) {
                    // Reembolsar o saldo
                    $u = User::where('id', $user->id)->lockForUpdate()->first();
                    $u->amount_available += $withdraw->gross_amount;
                    $u->save();

                    $withdraw->update([
                        'status' => 'failed',
                        'meta'   => $meta + ['error_type' => 'lumnis_error'],
                    ]);
                });

                Log::error('❌ Saque marcado como falho (erro Lumnis)', [
                    'withdraw_id' => $withdraw->id,
                    'reference'   => $reference,
                    'reason'      => data_get($receipt, 'refused_reason'),
                ]);

                $this->notifyClient($user, $withdraw, $payload, 'withdraw.failed');

                return response()->json([
                    'success'     => true,
                    'status'      => 'failed',
                    'reference'   => $reference,
                    'withdraw_id' => $withdraw->id,
                ]);
            }

            /* ============================================================
             * 7️⃣ CASO DE SUCESSO → MARCAR COMO PAGO
             * ============================================================ */
            if ($isFinalSuccess) {
                $withdraw->update([
                    'status'       => 'paid',
                    'processed_at' => now(),
                    'amount'       => $requestedReais ?: $withdraw->amount,
                    'meta'         => $meta + ['success_at' => now()->toIso8601String()],
                ]);

                Log::info('✅ Saque marcado como pago via Lumnis', [
                    'withdraw_id' => $withdraw->id,
                    'reference'   => $reference,
                    'endtoend'    => data_get($receipt, 'endtoend'),
                ]);

                $this->notifyClient($user, $withdraw, $payload, 'withdraw.paid');

                return response()->json([
                    'success'     => true,
                    'status'      => 'paid',
                    'reference'   => $reference,
                    'withdraw_id' => $withdraw->id,
                ]);
            }

            /* ============================================================
             * 8️⃣ STATUS INTERMEDIÁRIO → IGNORAR
             * ============================================================ */
            Log::info('⏸ Webhook Lumnis ignorado (status intermediário)', [
                'status' => $status,
                'reference' => $reference,
            ]);

            return response()->json([
                'success' => true,
                'ignored' => true,
                'status'  => $status,
            ]);
        } catch (\Throwable $e) {
            Log::error('🚨 Erro no processamento do Webhook Lumnis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'internal_error',
            ], 500);
        }
    }

    /**
     * 📤 Dispara webhook OUT para o cliente
     */
    private function notifyClient(?User $user, Withdraw $withdraw, array $payload, string $event)
    {
        if (!$user?->webhook_enabled || !$user?->webhook_out_url) {
            return;
        }

        try {
            if (isset($payload['operation']['postback'])) {
                unset($payload['operation']['postback']);
            }

            $payloadToClient = $payload;
            $payloadToClient['external_id'] = $withdraw->external_id;

            Http::timeout(10)->post($user->webhook_out_url, [
                'event' => $event,
                'data'  => $payloadToClient,
            ]);

            Log::info('📤 Webhook OUT enviado com sucesso', [
                'event'        => $event,
                'withdraw_id'  => $withdraw->id,
                'user_id'      => $user->id,
                'url'          => $user->webhook_out_url,
            ]);
        } catch (\Throwable $e) {
            Log::warning('⚠️ Falha ao enviar webhook OUT', [
                'event' => $event,
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
