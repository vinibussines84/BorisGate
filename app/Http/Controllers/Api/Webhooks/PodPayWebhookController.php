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

            /* ---------------------------------------------------------
             * 1️⃣ Normaliza payload
             * ---------------------------------------------------------*/
            $raw = $request->json()->all()
                ?: json_decode($request->getContent(), true)
                ?: [];

            Log::info("📩 Webhook PodPay PIX recebido", ['payload' => $raw]);

            $data = data_get($raw, 'data', []);

            $externalRef = data_get($data, 'externalRef');
            $txid        = data_get($data, 'id');
            $status      = strtolower(data_get($data, 'status', 'unknown'));

            if (!$externalRef && !$txid) {
                return response()->json(['error' => 'missing_reference'], 422);
            }

            /* ---------------------------------------------------------
             * 2️⃣ Buscar transação com LOCK
             * ---------------------------------------------------------*/
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

            /* ---------------------------------------------------------
             * 3️⃣ Idempotência
             * ---------------------------------------------------------*/
            if (in_array($tx->status, [
                TransactionStatus::PAGA->value,
                TransactionStatus::FALHA->value
            ])) {
                return response()->json(['ignored' => true]);
            }

            /* ---------------------------------------------------------
             * 4️⃣ Mapeamento real
             * ---------------------------------------------------------*/
            $map = [
                'paid'       => TransactionStatus::PAGA,
                'approved'   => TransactionStatus::PAGA,
                'confirmed'  => TransactionStatus::PAGA,
                'completed'  => TransactionStatus::PAGA,

                'pending'         => TransactionStatus::PENDENTE,
                'waiting'         => TransactionStatus::PENDENTE,
                'waiting_payment' => TransactionStatus::PENDENTE,

                'created'    => TransactionStatus::MED,
                'processing' => TransactionStatus::MED,
                'authorized' => TransactionStatus::MED,

                'failed'     => TransactionStatus::FALHA,
                'error'      => TransactionStatus::FALHA,
                'canceled'   => TransactionStatus::FALHA,
                'cancelled'  => TransactionStatus::FALHA,
                'denied'     => TransactionStatus::FALHA,
                'rejected'   => TransactionStatus::FALHA,
                'refused'    => TransactionStatus::FALHA,
                'returned'   => TransactionStatus::FALHA,
                'expired'    => TransactionStatus::FALHA,
            ];

            $newStatus = $map[$status] ?? null;

            if (!$newStatus) {
                return response()->json(['ignored' => true]);
            }

            $oldStatus = TransactionStatus::tryFrom($tx->status);

            /* ---------------------------------------------------------
             * 5️⃣ ATUALIZAÇÃO FINANCEIRA (não dispara observer)
             * ---------------------------------------------------------*/
            $wallet->applyStatusChange($tx, $oldStatus, $newStatus);

            /* ---------------------------------------------------------
             * 6️⃣ Atualizar TX sem acionar Observer
             * ---------------------------------------------------------*/
            $this->updateTransactionQuietly($tx, $newStatus, $data);

            /* ---------------------------------------------------------
             * 7️⃣ Enviar webhook IN ao cliente APENAS uma vez
             * ---------------------------------------------------------*/
            if (
                $newStatus === TransactionStatus::PAGA &&
                $tx->user?->webhook_enabled &&
                $tx->user?->webhook_in_url
            ) {
                SendWebhookPixUpdateJob::dispatch($tx->id);
            }

            return response()->json([
                'success' => true,
                'status'  => $newStatus->value,
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
     * Atualiza TX SEM disparar observer
     */
    private function updateTransactionQuietly(Transaction $tx, TransactionStatus $newStatus, array $data)
    {
        $paidCents   = (int) data_get($data, 'paidAmount', 0);
        $amountReais = round($paidCents / 100, 2);

        $endToEnd    = data_get($data, 'pix.end2EndId');
        $providerId  = data_get($data, 'id');
        $paidAt      = data_get($data, 'paidAt');

        $tx->updateQuietly([
            'status'                  => $newStatus->value,
            'paid_at'                 => $paidAt ?: $tx->paid_at,
            'provider_transaction_id' => $providerId,
            'e2e_id'                  => $endToEnd ?: $tx->e2e_id,
            'amount'                  => $amountReais ?: $tx->amount,
            'provider_payload'        => $data,
        ]);
    }
}
