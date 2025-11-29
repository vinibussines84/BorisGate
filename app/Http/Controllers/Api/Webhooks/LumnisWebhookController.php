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
            // 🔹 Tenta ler o JSON corretamente (mesmo se vier raw)
            $data = $request->json()->all();
            if (empty($data)) {
                $data = json_decode($request->getContent(), true) ?? [];
            }

            Log::info('📩 Webhook Lumnis recebido', $data);

            // 🔹 Captura referências de diferentes formatos possíveis
            $externalRef = trim((string) (
                data_get($data, 'external_ref')
                ?? data_get($data, 'externalRef')
                ?? data_get($data, 'external_reference')
            ));

            $txid = trim((string) (
                data_get($data, 'id')
                ?? data_get($data, 'transaction_id')
            ));

            $status = strtoupper((string) data_get($data, 'status', ''));

            if (!$externalRef && !$txid) {
                return response()->json(['error' => 'Missing external_ref or txid'], 422);
            }

            // 🔍 Busca por external_reference ou txid
            $tx = Transaction::query()
                ->where('external_reference', $externalRef)
                ->when(!$externalRef && $txid, fn($q) => $q->where('txid', $txid))
                ->orWhere('txid', $txid)
                ->first();

            if (!$tx) {
                Log::warning('⚠️ Transação não encontrada para webhook', [
                    'external_ref' => $externalRef,
                    'txid' => $txid,
                ]);
                return response()->json(['error' => 'Transaction not found'], 404);
            }

            // 🚫 Evita reprocessar transações já pagas
            if ($tx->isPaga()) {
                return response()->json([
                    'received' => true,
                    'ignored'  => true,
                    'reason'   => 'already_paid',
                ]);
            }

            // ✅ Atualiza apenas quando aprovado/pago
            if (in_array($status, ['APPROVED', 'PAID', 'CONFIRMED'])) {
                $tx->status          = TransactionStatus::PAGA->value;
                $tx->paid_at         = now();
                $tx->e2e_id          = data_get($data, 'endtoend') ?? $tx->e2e_id;
                $tx->payer_name      = data_get($data, 'payer_name') ?? $tx->payer_name;
                $tx->payer_document  = data_get($data, 'payer_document') ?? $tx->payer_document;
                $tx->provider_payload = json_encode($data);
                $tx->save();

                Log::info('✅ Transação atualizada com sucesso via webhook', [
                    'id' => $tx->id,
                    'external_reference' => $tx->external_reference,
                    'status' => $tx->status,
                ]);

                return response()->json([
                    'received' => true,
                    'updated'  => true,
                    'status'   => 'paga',
                    'e2e_id'   => $tx->e2e_id,
                ]);
            }

            // 🔸 Outros status apenas são logados e ignorados
            Log::info('ℹ️ Webhook recebido com status não aprovado', [
                'status' => $status,
                'external_ref' => $externalRef,
            ]);

            return response()->json([
                'received' => true,
                'ignored'  => true,
                'reason'   => 'status_not_approved',
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ Erro no processamento do Webhook Lumnis', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
