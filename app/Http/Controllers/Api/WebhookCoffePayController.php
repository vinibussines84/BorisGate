<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus; // important
use App\Jobs\SendWebhookPixUpdateJob;

class WebhookCoffePayController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::info("📩 COFFE PAY WEBHOOK RECEIVED", [
            'payload' => $payload,
            'headers' => $request->headers->all()
        ]);

        $providerId = $payload['id'] ?? null;
        $externalId = $payload['external_id'] ?? null;
        $txId       = $payload['txId'] ?? null;

        if (!$providerId && !$txId && !$externalId) {
            return response()->json(['error' => 'missing_reference'], 422);
        }

        // Buscar transação por external_id OU provider_transaction_id
        $tx = Transaction::query()
            ->when($externalId, fn ($q) => $q->where('external_reference', $externalId))
            ->when(!$externalId && $providerId, fn ($q) => $q->where('provider_transaction_id', $providerId))
            ->when(!$externalId && !$providerId && $txId, fn ($q) => $q->where('txid', $txId))
            ->first();

        if (!$tx) {
            Log::warning("⚠️ TX não encontrada para CoffePay Webhook", [
                'providerId' => $providerId,
                'txId'       => $txId,
                'externalId' => $externalId,
            ]);
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        /**
         * ---------------------------------------------------------
         * MAPEAR STATUS COFFE PAY → STATUS DO SISTEMA
         * ---------------------------------------------------------
         */
        $statusProvider = strtolower($payload['status'] ?? 'pending');

        $map = [
            'paid'       => TransactionStatus::PAGA->value,       // "PAID"
            'completed'  => TransactionStatus::PAGA->value,
            'success'    => TransactionStatus::PAGA->value,

            'pending'    => TransactionStatus::PENDENTE->value,   // "PENDING"
            'waiting'    => TransactionStatus::PENDENTE->value,

            'processing' => TransactionStatus::MED->value,        // "PROCESSING"

            'failed'     => TransactionStatus::FALHA->value,      // "FAILED"
            'canceled'   => TransactionStatus::FALHA->value,
            'cancelled'  => TransactionStatus::FALHA->value,
            'error'      => TransactionStatus::FALHA->value,
        ];

        $newStatus = $map[$statusProvider] ?? TransactionStatus::PENDENTE->value;

        // Atualiza dados da transação
        $tx->updateQuietly([
            'status'                  => $newStatus,
            'paid_at'                 => $payload['paid_at'] ?? $tx->paid_at,
            'provider_transaction_id' => $providerId,
            'e2e_id'                  => data_get($payload, 'pix.end_to_end'),
            'provider_payload'        => $payload,
        ]);

        Log::info("🔄 TX atualizada via CoffePay Webhook", [
            'id'     => $tx->id,
            'status' => $newStatus,
        ]);

        // 🔔 Enviar webhook APENAS quando PAID
        if ($newStatus === TransactionStatus::PAGA->value) {
            SendWebhookPixUpdateJob::dispatch($tx->id);
        }

        return response()->json([
            'success' => true,
            'status'  => $newStatus
        ]);
    }
}
