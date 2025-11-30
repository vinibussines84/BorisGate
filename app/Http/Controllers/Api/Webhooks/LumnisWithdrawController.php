<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;
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
             * 1️⃣ STATUS (PENDING | APPROVED | PAID)
             * ============================================================ */
            $status = strtolower(data_get($payload, 'status', 'unknown'));

            // Somente atualiza quando o status final for aprovado/pago
            if (!in_array($status, ['approved', 'paid'])) {
                return response()->json([
                    'ignored' => true,
                    'reason'  => 'status_not_final',
                    'status'  => $status,
                ], 200);
            }

            /* ============================================================
             * 2️⃣ PEGAR O IDENTIFICADOR UNIFICADO (robusto)
             * ============================================================ */
            $reference =
                   data_get($payload, 'id')                             // ID principal (normal)
                ?? data_get($payload, 'data.id')                        // fallback alternativo
                ?? data_get($payload, 'receipt.0.identifier')           // se vier no array receipt
                ?? data_get($payload, 'receipt.0.id')                   // backup raro
                ?? null;

            if (!$reference) {
                Log::warning('⚠️ Webhook Lumnis: referência ausente', ['payload' => $payload]);
                return response()->json(['ignored' => true, 'reason' => 'missing_reference'], 200);
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
                // Retorna 200 para evitar reenvio infinito pela Lumnis
                return response()->json(['ignored' => true, 'reason' => 'withdraw_not_found'], 200);
            }

            /* ============================================================
             * 4️⃣ DADOS DO WEBHOOK
             * ============================================================ */
            $receipt = (array) data_get($payload, 'receipt.0', []);

            $requestedCents = (int) data_get($payload, 'requested', 0);
            $paidCents      = (int) data_get($payload, 'paid', 0);
            $opCents        = (int) data_get($payload, 'operation.amount', 0);

            $requestedReais = $requestedCents / 100;
            $paidReais      = $paidCents / 100;
            $opAmount       = $opCents / 100;

            $endtoend     = data_get($receipt, 'endtoend');
            $identifier   = data_get($receipt, 'identifier');
            $receiverName = data_get($receipt, 'receiver_name');
            $receiverBank = data_get($receipt, 'receiver_bank');
            $receiverIspb = data_get($receipt, 'receiver_bank_ispb');
            $refReason    = data_get($receipt, 'refused_reason');

            /* ============================================================
             * 5️⃣ IDEMPOTÊNCIA (não reprocessar já pagos)
             * ============================================================ */
            if (in_array($withdraw->status, ['paid', 'approved'])) {
                Log::info('ℹ️ Webhook Lumnis ignorado (já estava pago)', [
                    'withdraw_id' => $withdraw->id,
                    'reference'   => $reference,
                ]);
                return response()->json(['ignored' => true, 'reason' => 'already_paid'], 200);
            }

            /* ============================================================
             * 6️⃣ ATUALIZAR O SAQUE
             * ============================================================ */
            $meta = (array) $withdraw->meta;
            $meta = array_merge($meta, [
                'raw_provider_payload' => $payload,
                'requested_reais'      => $requestedReais,
                'paid_reais'           => $paidReais,
                'operation_reais'      => $opAmount,
                'endtoend'             => $endtoend,
                'identifier'           => $identifier,
                'receiver_name'        => $receiverName,
                'receiver_bank'        => $receiverBank,
                'receiver_bank_ispb'   => $receiverIspb,
                'refused_reason'       => $refReason,
                'paid_at'              => now()->toIso8601String(),
            ]);

            $withdraw->update([
                'status'       => 'paid',
                'processed_at' => now(),
                'amount'       => $requestedReais ?: $withdraw->amount,
                'meta'         => $meta,
            ]);

            Log::info('✅ Saque atualizado via webhook Lumnis', [
                'withdraw_id' => $withdraw->id,
                'reference'   => $reference,
                'status'      => $status,
            ]);

            /* ============================================================
             * 7️⃣ ENVIAR WEBHOOK OUT PARA O CLIENTE
             * ============================================================ */
            $user = User::find($withdraw->user_id);

            if ($user?->webhook_enabled && $user?->webhook_out_url) {
                // limpar postback antes de enviar
                if (isset($payload['operation']['postback'])) {
                    unset($payload['operation']['postback']);
                }

                $payloadToClient = $payload;
                $payloadToClient['requested'] = $requestedReais;
                $payloadToClient['paid']      = $paidReais;
                $payloadToClient['operation']['amount'] = $opAmount;
                $payloadToClient['external_id'] = $withdraw->external_id;

                try {
                    Http::timeout(10)->post($user->webhook_out_url, [
                        'event' => 'withdraw.updated',
                        'data'  => $payloadToClient,
                    ]);

                    Log::info('📤 Webhook OUT enviado ao cliente com sucesso', [
                        'withdraw_id'  => $withdraw->id,
                        'user_id'      => $user->id,
                        'url'          => $user->webhook_out_url,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('⚠️ Falha ao enviar webhook OUT para cliente', [
                        'user_id' => $user->id,
                        'url'     => $user->webhook_out_url,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            /* ============================================================
             * 8️⃣ RESPOSTA FINAL PARA LUMNIS
             * ============================================================ */
            return response()->json([
                'received'     => true,
                'status'       => $status,
                'reference'    => $reference,
                'external_id'  => $withdraw->external_id,
                'withdraw_id'  => $withdraw->id,
                'user_id'      => $withdraw->user_id,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('❌ Erro no processamento do Webhook Lumnis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
