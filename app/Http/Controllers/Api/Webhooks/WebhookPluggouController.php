<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Support\StatusMap;
use App\Jobs\SendWebhookPixUpdateJob;

class WebhookPluggouController extends Controller
{
    public function __invoke(Request $request)
    {
        try {

            /* ---------------------------------------------------------
             * 1️⃣ Normaliza payload
             * ---------------------------------------------------------*/
            $raw = $request->json()->all()
                ?: json_decode($request->getContent(), true)
                ?: [];

            Log::info("📩 Webhook Pluggou recebido", ['payload' => $raw]);

            $eventData  = data_get($raw, 'data', []);
            $providerId = data_get($eventData, 'id');          // ID da Pluggou
            $statusRaw  = strtolower(data_get($eventData, 'status', 'unknown'));
            $e2e        = data_get($eventData, 'e2e_id');
            $paidAt     = data_get($eventData, 'paid_at');

            if (!$providerId) {
                Log::warning("⚠️ provider_transaction_id ausente");
                return response()->json(['error' => 'missing_provider_transaction_id'], 422);
            }

            /* ---------------------------------------------------------
             * 2️⃣ Buscar TX por provider_transaction_id
             * ---------------------------------------------------------*/
            $tx = Transaction::query()
                ->where('provider_transaction_id', $providerId)
                ->lockForUpdate()
                ->first();

            if (!$tx) {
                Log::warning("⚠️ TX não encontrada para webhook Pluggou", [
                    'provider_transaction_id' => $providerId,
                ]);
                return response()->json(['error' => 'transaction_not_found'], 404);
            }

            /* ---------------------------------------------------------
             * 3️⃣ Idempotência — TX finalizada não deve ser alterada
             * ---------------------------------------------------------*/
            if (in_array($tx->status, ['PAID', 'FAILED'])) {
                Log::info("ℹ️ Webhook ignorado — TX já finalizada", [
                    'tx_id'  => $tx->id,
                    'status' => $tx->status,
                ]);
                return response()->json(['ignored' => true]);
            }

            /* ---------------------------------------------------------
             * 4️⃣ Mapear status do provedor → StatusMap do sistema
             * ---------------------------------------------------------*/
            $newStatus = StatusMap::normalize($statusRaw);

            /* ---------------------------------------------------------
             * 5️⃣ Atualizar a transação
             * ---------------------------------------------------------*/
            $tx->updateQuietly([
                'status'                  => $newStatus,
                'provider_payload'        => $eventData,
                'e2e_id'                  => $e2e ?: $tx->e2e_id,
                'paid_at'                 => $paidAt ?: $tx->paid_at,
            ]);

            Log::info("✅ TX atualizada via webhook Pluggou", [
                'tx_id'       => $tx->id,
                'new_status'  => $newStatus,
                'e2e'         => $tx->e2e_id,
                'paid_at'     => $tx->paid_at,
            ]);

            /* ---------------------------------------------------------
             * 6️⃣ Disparar webhook interno APENAS quando pago
             * ---------------------------------------------------------*/
            if (
                $newStatus === 'PAID' &&
                $tx->user?->webhook_enabled &&
                $tx->user?->webhook_in_url
            ) {
                SendWebhookPixUpdateJob::dispatch($tx->id);
                Log::info("🚀 Webhook interno disparado ao cliente", [
                    'tx_id' => $tx->id
                ]);
            }

            return response()->json([
                'success' => true,
                'status'  => $newStatus,
            ]);

        } catch (\Throwable $e) {

            Log::error("🚨 ERRO NO WEBHOOK PLUGGOU", [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            return response()->json(['error' => 'internal_server_error'], 500);
        }
    }
}
