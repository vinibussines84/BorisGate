<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Support\StatusMap;
use App\Enums\TransactionStatus;
use App\Jobs\SendWebhookPixUpdateJob;
use App\Services\WalletService;

class WebhookPluggouController extends Controller
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

            Log::info("📩 Webhook Pluggou recebido", ['payload' => $raw]);

            $data       = data_get($raw, 'data', []);
            $providerId = data_get($data, 'id');
            $statusRaw  = strtolower(data_get($data, 'status', 'unknown'));
            $e2e        = data_get($data, 'e2e_id');
            $paidAt     = data_get($data, 'paid_at');

            if (!$providerId) {
                Log::warning("⚠️ Webhook Pluggou sem provider_transaction_id");
                return response()->json(['error' => 'missing_provider_transaction_id'], 422);
            }

            /* ---------------------------------------------------------
             * 2️⃣ Buscar transação
             * ---------------------------------------------------------*/
            $tx = Transaction::where('provider_transaction_id', $providerId)
                ->lockForUpdate()
                ->first();

            if (!$tx) {
                Log::warning("⚠️ TX não encontrada para Webhook Pluggou", [
                    'provider_transaction_id' => $providerId
                ]);
                return response()->json(['error' => 'transaction_not_found'], 404);
            }

            /* ---------------------------------------------------------
             * 3️⃣ Idempotência
             * ---------------------------------------------------------*/
            if (in_array($tx->status, ['PAID', 'FAILED'], true)) {
                return response()->json(['ignored' => true]);
            }

            /* ---------------------------------------------------------
             * 4️⃣ Normalizar status usando StatusMap
             * ---------------------------------------------------------*/
            $normalized = StatusMap::normalize($statusRaw); // string exemplo: 'PAID'
            $newEnum    = TransactionStatus::tryFrom($normalized);

            if (!$newEnum) {
                Log::info("ℹ️ Status não mapeado Pluggou", [
                    'status_raw' => $statusRaw
                ]);
                return response()->json(['ignored' => true]);
            }

            $oldEnum = TransactionStatus::tryFrom($tx->status);

            /* ---------------------------------------------------------
             * 5️⃣ Aplicar lógica financeira (saldo, bloqueios etc.)
             * ---------------------------------------------------------*/
            $wallet->applyStatusChange($tx, $oldEnum, $newEnum);

            /* ---------------------------------------------------------
             * 6️⃣ Atualizar TX silenciosamente
             * ---------------------------------------------------------*/
            $tx->updateQuietly([
                'status'           => $newEnum->value,
                'provider_payload' => $data,
                'e2e_id'           => $e2e ?: $tx->e2e_id,
                'paid_at'          => $paidAt ?: $tx->paid_at,
            ]);

            Log::info("✅ TX atualizada via Webhook Pluggou", [
                'tx_id'      => $tx->id,
                'old_status' => $oldEnum?->value,
                'new_status' => $newEnum->value,
            ]);

            /* ---------------------------------------------------------
             * 7️⃣ Disparar webhook ao cliente somente quando pago
             * ---------------------------------------------------------*/
            if (
                $newEnum === TransactionStatus::PAGA &&
                $tx->user?->webhook_enabled &&
                $tx->user?->webhook_in_url
            ) {
                SendWebhookPixUpdateJob::dispatch($tx->id);

                Log::info("🚀 Webhook interno enviado ao cliente", [
                    'tx_id' => $tx->id
                ]);
            }

            return response()->json([
                'success' => true,
                'status'  => $newEnum->value,
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
