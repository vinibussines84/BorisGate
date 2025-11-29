<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Events\PixTransactionPaid;

/**
 * Controlador responsável por processar notificações (webhooks) da ReflowPay.
 */
class ReflowPayWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = $request->all();

        Log::info('Webhook ReflowPay recebido', $payload);

        $transactionId = data_get($payload, 'transactionId');
        $orderId       = data_get($payload, 'orderId');
        $status        = data_get($payload, 'status');
        $e2e           = data_get($payload, 'endToEndId');
        $valorCentavos = data_get($payload, 'value');
        $valorReais    = $valorCentavos ? ($valorCentavos / 100) : null;

        // =====================================================================
        // 1. Ignorar webhooks sem IDs válidos
        // =====================================================================
        if (!$transactionId || !$orderId) {
            Log::warning('Webhook ReflowPay ignorado — sem transactionId/orderId');
            return response()->json(['ignored' => true]);
        }

        // =====================================================================
        // 2. Ignorar status "created"
        // =====================================================================
        if ($status === 'created') {
            Log::info("Webhook ReflowPay ignorado (created) para {$orderId}");
            return response()->json(['ignored' => true]);
        }

        // =====================================================================
        // 3. Buscar transação correspondente
        // =====================================================================
        $tx = Transaction::where('external_reference', $orderId)->first();

        if (!$tx) {
            Log::error("Webhook ReflowPay: transação não encontrada para orderId {$orderId}");
            return response()->json(['error' => true]);
        }

        // =====================================================================
        // 4. Evitar duplicidade
        // =====================================================================
        if ($tx->isPaga()) {
            Log::info("Webhook ReflowPay duplicado ignorado — transação já paga ({$orderId})");
            return response()->json(['duplicated' => true]);
        }

        // =====================================================================
        // 5. Valores acima de R$50 entram em revisão
        // =====================================================================
        if ($valorReais !== null && $valorReais > 50) {
            DB::transaction(function () use ($tx, $transactionId, $e2e, $payload, $valorReais, $orderId) {
                $tx->provider_transaction_id = $transactionId;
                $tx->e2e_id = $e2e;
                $tx->provider_payload = array_merge($tx->provider_payload ?? [], ['webhook' => $payload]);
                $tx->status = TransactionStatus::UNDER_REVIEW->value;
                $tx->save();

                Log::warning("Transação {$orderId} movida para UNDER_REVIEW — valor R\${$valorReais}");
            });

            return response()->json(['review' => true]);
        }

        // =====================================================================
        // 6. Marcar como paga
        // =====================================================================
        if ($status === 'paid') {
            DB::transaction(function () use ($tx, $transactionId, $e2e, $payload, $orderId) {
                $tx->provider_transaction_id = $transactionId;
                $tx->e2e_id = $e2e;
                $tx->provider_payload = array_merge($tx->provider_payload ?? [], ['webhook' => $payload]);
                $tx->markPaid();

                Log::info("Transação {$orderId} marcada como PAGA — E2E={$e2e}");

                // ===============================================================
                // 7. Disparar evento de notificação em tempo real
                // ===============================================================
                try {
                    if ($tx->user_id) {
                        broadcast(new PixTransactionPaid($tx))->toOthers();
                        Log::info("Evento PixTransactionPaid disparado para canal user.{$tx->user_id}");
                    } else {
                        Log::warning("Transação {$tx->id} sem user_id — evento não emitido.");
                    }
                } catch (\Throwable $e) {
                    Log::error("Erro ao emitir evento PixTransactionPaid: " . $e->getMessage());
                }
            });

            return response()->json(['success' => true]);
        }

        // =====================================================================
        // 8. Status inesperado
        // =====================================================================
        Log::warning("Webhook ReflowPay com status desconhecido: {$status}");
        return response()->json(['ignored' => true]);
    }
}
