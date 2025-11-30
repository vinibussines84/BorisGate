<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Jobs\SendWebhookPixUpdateJob;

class PodPayWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            /* ============================================================
             * 1️⃣ Normaliza payload
             * ============================================================ */
            $raw = $request->json()->all();
            if (!$raw) {
                $raw = json_decode($request->getContent(), true) ?? [];
            }

            Log::info("📩 Webhook PodPay recebido", $raw);

            $data = data_get($raw, 'data', []);

            $externalRef = data_get($data, 'externalRef');
            $txid        = data_get($data, 'id');
            $status      = strtoupper(data_get($data, 'status', 'UNKNOWN'));

            if (!$externalRef && !$txid) {
                return response()->json(['error' => 'missing_reference'], 422);
            }

            /* ============================================================
             * 2️⃣ Localiza transação (lock para evitar concorrência)
             * ============================================================ */
            $tx = Transaction::query()
                ->when($externalRef, fn($q) => $q->where('external_reference', $externalRef))
                ->when(!$externalRef && $txid, fn($q) => $q->where('txid', $txid))
                ->lockForUpdate()
                ->first();

            if (!$tx) {
                Log::warning("⚠️ TX não encontrada (PodPay)", [
                    'externalRef' => $externalRef,
                    'txid'        => $txid,
                ]);
                return response()->json(['error' => 'Transaction not found'], 404);
            }

            /* ============================================================
             * 3️⃣ Ignora duplicações (idempotência)
             * ============================================================ */
            if ($tx->isPaga()) {
                Log::info("ℹ️ Ignorado: transação já paga", ['tx_id' => $tx->id]);
                return response()->json(['ignored' => true, 'reason' => 'already_paid']);
            }

            /* ============================================================
             * 4️⃣ Extrai informações úteis
             * ============================================================ */
            $endToEnd = data_get($data, 'pix.end2EndId');
            $paidCents = (int) data_get($data, 'paidAmount', 0);
            $amountReais = round($paidCents / 100, 2);
            $paidAt = data_get($data, 'paidAt');

            /* ============================================================
             * 5️⃣ Ignora status intermediários
             * ============================================================ */
            $nonFinal = ['WAITING_PAYMENT', 'PENDING', 'CREATED', 'PROCESSING', 'AUTHORIZED'];
            if (in_array($status, $nonFinal)) {
                Log::info("⏸ Ignorado: status intermediário {$status}", [
                    'tx_id' => $tx->id,
                    'status' => $status,
                ]);
                return response()->json(['ignored' => true, 'reason' => 'non_final_status']);
            }

            /* ============================================================
             * 6️⃣ Status final: pago ou falhou
             * ============================================================ */
            if (in_array($status, ['PAID', 'APPROVED', 'CONFIRMED'])) {
                $cleanPayload = [
                    "id"            => data_get($data, 'id'),
                    "type"          => data_get($data, 'type', 'transaction'),
                    "paymentMethod" => data_get($data, 'paymentMethod'),
                    "status"        => data_get($data, 'status'),
                    "paidAt"        => $paidAt,
                    "paidAmount"    => $paidCents,
                    "paidReais"     => $amountReais, // ✅ convertido
                    "pix" => [
                        "qrcode"    => data_get($data, 'pix.qrcode'),
                        "end2EndId" => $endToEnd,
                    ],
                ];

                DB::transaction(function () use ($tx, $txid, $endToEnd, $cleanPayload, $amountReais, $paidAt) {
                    $tx->update([
                        'status'                 => TransactionStatus::PAGA->value,
                        'paid_at'                => $paidAt ? now() : now(),
                        'e2e_id'                 => $endToEnd ?: $tx->e2e_id,
                        'provider_transaction_id'=> $txid,
                        'amount'                 => $amountReais ?: $tx->amount, // ✅ corrigido
                        'provider_payload'       => $cleanPayload,
                    ]);
                });

                Log::info("✅ PodPay: transação confirmada como PAGA", [
                    'transaction_id' => $tx->id,
                    'externalRef'    => $tx->external_reference,
                    'valor_reais'    => $amountReais,
                ]);

                if ($tx->user?->webhook_enabled && $tx->user?->webhook_in_url) {
                    SendWebhookPixUpdateJob::dispatch($tx->id);
                }

                return response()->json(['success' => true, 'status' => 'paid']);
            }

            if (in_array($status, ['FAILED', 'ERROR', 'CANCELED'])) {
                DB::transaction(function () use ($tx, $txid, $status, $data) {
                    $tx->update([
                        'status'                 => TransactionStatus::FALHOU->value,
                        'provider_transaction_id'=> $txid,
                        'provider_payload'       => $data,
                    ]);
                });

                Log::warning("❌ PodPay: transação marcada como FALHOU", [
                    'transaction_id' => $tx->id,
                    'status' => $status,
                ]);

                return response()->json(['success' => true, 'status' => 'failed']);
            }

            /* ============================================================
             * 7️⃣ Status desconhecido
             * ============================================================ */
            Log::warning("⚠️ Webhook PodPay com status desconhecido", [
                'status' => $status,
                'tx_id'  => $tx->id,
            ]);

            return response()->json(['ignored' => true, 'reason' => 'unknown_status']);

        } catch (\Throwable $e) {

            Log::error("🚨 ERRO NO WEBHOOK PODPAY", [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
