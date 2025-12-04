<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Support\StatusMap;
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
        $txId       = $payload['txId'] ?? null;
        $status     = $payload['status'] ?? null;

        if (!$providerId && !$txId) {
            return response()->json(['error' => 'missing_reference'], 422);
        }

        // buscar transação
        $tx = Transaction::query()
            ->where('provider_transaction_id', $providerId)
            ->orWhere('txid', $txId)
            ->first();

        if (!$tx) {
            Log::warning("⚠️ TX não encontrada para CoffePay webhook", [
                'provider_id' => $providerId,
                'txid'        => $txId,
            ]);
            return response()->json(['error' => 'not_found'], 404);
        }

        // Normalizar status
        $normalized = StatusMap::normalize($status);

        // Idempotência
        if (in_array($tx->status, ['PAID', 'FAILED'], true)) {
            return response()->json(['ignored' => true]);
        }

        // Atualizar transação
        $tx->updateQuietly([
            'status'                  => $normalized,
            'paid_at'                 => $payload['paid_at'] ?? $tx->paid_at,
            'provider_transaction_id' => $providerId,
            'e2e_id'                  => $payload['pix']['end_to_end'] ?? $tx->e2e_id,
            'provider_payload'        => $payload,
            'amount'                  => $payload['amount'] ?? $tx->amount,
        ]);

        Log::info("🔄 TX atualizada via CoffePay Webhook", [
            'id'     => $tx->id,
            'status' => $normalized,
        ]);

        // Dispara webhook do client APENAS SE PAGO
        if ($normalized === 'PAID') {
            SendWebhookPixUpdateJob::dispatch($tx->id);
        }

        return response()->json(['success' => true]);
    }
}
