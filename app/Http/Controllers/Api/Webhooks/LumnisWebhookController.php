<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;

class LumnisWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            // Lê corpo JSON do webhook (mesmo que venha raw)
            $data = $request->json()->all();
            if (empty($data)) {
                $data = json_decode($request->getContent(), true) ?? [];
            }

            $externalRef = trim((string) data_get($data, 'external_ref'));
            $status      = strtoupper((string) data_get($data, 'status', ''));

            if (!$externalRef) {
                return response()->json(['error' => 'Missing external_ref'], 422);
            }

            // Busca tanto por external_reference quanto por txid
            $tx = Transaction::query()
                ->where('external_reference', $externalRef)
                ->orWhere('txid', (string) $externalRef)
                ->first();

            if (!$tx) {
                return response()->json(['error' => 'Transaction not found'], 404);
            }

            // 🚫 Se já estiver paga, ignora novas notificações
            if ($tx->isPaga()) {
                return response()->json([
                    'received' => true,
                    'ignored'  => true,
                    'reason'   => 'already_paid',
                ]);
            }

            // ✅ Apenas quando o status for APPROVED
            if ($status === 'APPROVED') {
                $tx->status  = TransactionStatus::PAGA->value;
                $tx->paid_at = now();
                $tx->save();

                return response()->json([
                    'received' => true,
                    'updated'  => true,
                    'status'   => 'paga',
                ]);
            }

            // Qualquer outro status é recebido, mas não altera nada
            return response()->json([
                'received' => true,
                'ignored'  => true,
                'reason'   => 'status_not_approved',
            ]);
        } catch (\Throwable $e) {
            // Log apenas para erros reais de execução
            Log::error('❌ Erro no processamento do Webhook Lumnis', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
