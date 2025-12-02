<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Jobs\SendWebhookPixUpdateJob;
use App\Services\WalletService;

class PodPayWebhookController extends Controller
{
    public function __invoke(Request $request, WalletService $wallet)
    {
        try {
            /* ============================================================
             * 1️⃣ Normaliza payload
             * ============================================================ */
            $raw = $request->json()->all() 
                ?: json_decode($request->getContent(), true) 
                ?: [];

            Log::info("📩 Webhook PodPay PIX recebido", ['payload' => $raw]);

            $data = data_get($raw, 'data', []);

            $externalRef = data_get($data, 'externalRef');
            $txid        = data_get($data, 'id');
            $status      = strtoupper(data_get($data, 'status', 'UNKNOWN'));

            if (!$externalRef && !$txid) {
                return response()->json(['error' => 'missing_reference'], 422);
            }

            /* ============================================================
             * 2️⃣ Buscar transação com LOCK
             * ============================================================ */
            $tx = Transaction::query()
                ->when($externalRef, fn($q) => $q->where('external_reference', $externalRef))
                ->when(!$externalRef && $txid, fn($q) => $q->where('txid', $txid))
                ->lockForUpdate()
                ->first();

            if (!$tx) {
                Log::warning("⚠️ TX não encontrada para webhook PodPay", [
                    'externalRef' => $externalRef,
                    'txid'        => $txid,
                ]);
                return response()->json(['error' => 'Transaction not found'], 404);
            }

            /* ============================================================
             * 3️⃣ Idempotência — se já é final, não processa denovo
             * ============================================================ */
            if (in_array($tx->status, [
                TransactionStatus::PAGA->value,
                TransactionStatus::FALHOU->value
            ])) {
                Log::info("ℹ️ Webhook ignorado: TX já finalizada", [
                    'tx_id'  => $tx->id,
                    'status' => $tx->status
                ]);
                return response()->json(['ignored' => true]);
            }

            /* ============================================================
             * 4️⃣ Converte status PodPay → status interno
             * ============================================================ */
            $map = [
                'WAITING_PAYMENT' => TransactionStatus::PENDENTE,
                'PENDING'         => TransactionStatus::PENDENTE,
                'CREATED'         => TransactionStatus::MED,
                'PROCESSING'      => TransactionStatus::MED,
                'AUTHORIZED'      => TransactionStatus::MED,
                'PAID'            => TransactionStatus::PAGA,
                'APPROVED'        => TransactionStatus::PAGA,
                'CONFIRMED'       => TransactionStatus::PAGA,

                // Falhas
                'FAILED'          => TransactionStatus::FALHA,
                'ERROR'           => TransactionStatus::FALHA,
                'CANCELED'        => TransactionStatus::FALHA,
                'CANCELLED'       => TransactionStatus::FALHA,
                'DENIED'          => TransactionStatus::FALHA,
                'REJECTED'        => TransactionStatus::FALHA,
                'REFUSED'         => TransactionStatus::FALHA,
                'RETURNED'        => TransactionStatus::FALHA,
                'EXPIRED'         => TransactionStatus::FALHA,
            ];

            $newStatus = $map[$status] ?? null;

            if (!$newStatus) {
                Log::warning("⚠️ Status desconhecido recebido da PodPay", [
                    'status' => $status,
                    'tx_id'  => $tx->id
                ]);
                return response()->json(['ignored' => true]);
            }

            $oldStatus = TransactionStatus::tryFrom($tx->status);

            /* ============================================================
             * 5️⃣ Ajustar carteira (wallet) conforme mudança de status
             * ============================================================ */
            // MUITO IMPORTANTE: não atualize a TX antes do wallet
            $wallet->applyStatusChange($tx, $oldStatus, $newStatus);

            /* ============================================================
             * 6️⃣ Atualizar transação no banco
             * ============================================================ */
            $this->updateTransaction($tx, $newStatus, $data);

            /* ============================================================
             * 7️⃣ PIX IN — Enviar Webhook PARA O CLIENTE se foi pago
             * ============================================================ */
            if ($newStatus === TransactionStatus::PAGA &&
                $tx->user?->webhook_enabled &&
                $tx->user?->webhook_in_url
            ) {
                SendWebhookPixUpdateJob::dispatch($tx);
            }

            return response()->json([
                'success' => true,
                'status'  => $newStatus->value
            ]);

        } catch (\Throwable $e) {

            Log::error("🚨 ERRO NO WEBHOOK PODPAY PIX", [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }

    /**
     * Atualiza a Transaction no banco de forma segura
     */
    private function updateTransaction(Transaction $tx, TransactionStatus $newStatus, array $data)
    {
        DB::transaction(function () use ($tx, $newStatus, $data) {

            $paidCents   = (int) data_get($data, 'paidAmount', 0);
            $amountReais = round($paidCents / 100, 2);
            $endToEnd    = data_get($data, 'pix.end2EndId');
            $providerId  = data_get($data, 'id');
            $paidAt      = data_get($data, 'paidAt');

            $tx->update([
                'status'                  => $newStatus->value,
                'paid_at'                 => $paidAt ?: $tx->paid_at,
                'provider_transaction_id' => $providerId,
                'e2e_id'                  => $endToEnd ?: $tx->e2e_id,
                'amount'                  => $amountReais ?: $tx->amount,
                'provider_payload'        => $data,
            ]);
        });
    }
}
