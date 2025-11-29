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
            /** ==========================================================
             *  1) Normaliza payload (raw + JSON)
             *  ========================================================== */
            $data = $request->json()->all();
            if (empty($data)) {
                $data = json_decode($request->getContent(), true) ?? [];
            }

            Log::info('📩 Webhook Lumnis recebido', $data);

            /** ==========================================================
             *  2) Captura referências permitidas
             *  ========================================================== */
            $externalRef = trim((string)(
                data_get($data, 'external_ref')
                ?? data_get($data, 'externalRef')
                ?? data_get($data, 'external_reference')
            ));

            $txid = trim((string)(
                data_get($data, 'id')
                ?? data_get($data, 'transaction_id')
                ?? data_get($data, 'txid')
            ));

            $status = strtoupper((string)data_get($data, 'status'));

            if (!$externalRef && !$txid) {
                return response()->json(['error' => 'Missing external_ref or txid'], 422);
            }

            /** ==========================================================
             *  3) Busca transação de forma segura
             *  ========================================================== */
            $tx = Transaction::query()
                ->when($externalRef, fn($q) => $q->where('external_reference', $externalRef))
                ->when(!$externalRef && $txid, fn($q) => $q->where('txid', $txid))
                ->first();

            if (!$tx) {
                Log::warning('⚠️ Transação não encontrada', [
                    'external_ref' => $externalRef,
                    'txid'         => $txid,
                ]);

                return response()->json(['error' => 'Transaction not found'], 404);
            }

            /** ==========================================================
             *  4) Idempotência — ignora se já paga
             *  ========================================================== */
            if ($tx->isPaga()) {
                return response()->json([
                    'received' => true,
                    'ignored'  => true,
                    'reason'   => 'already_paid',
                ]);
            }

            /** ==========================================================
             *  5) Mapeia os dados enviados pela Lumnis
             *  ========================================================== */
            $payerName     = data_get($data, 'payer_name');
            $payerDocument = data_get($data, 'payer_document');
            $endToEnd      = data_get($data, 'endtoend');
            $providerId    = data_get($data, 'identifier') ?? data_get($data, 'id');

            /** ==========================================================
             *  6) Atualiza somente quando o status for aprovado
             *  ========================================================== */
            if (in_array($status, ['APPROVED', 'PAID', 'CONFIRMED'])) {

                $tx->update([
                    'status'                 => TransactionStatus::PAGA->value,
                    'paid_at'                => now(),
                    'e2e_id'                 => $endToEnd ?: $tx->e2e_id,
                    'payer_name'             => $payerName ?: $tx->payer_name,
                    'payer_document'         => $payerDocument ?: $tx->payer_document,
                    'provider_transaction_id'=> $providerId,
                    'provider_payload'       => $data, // salva JSON puro
                ]);

                Log::info('✅ Transação atualizada com sucesso!', [
                    'transaction_id' => $tx->id,
                    'status'         => $tx->status,
                    'external_ref'   => $tx->external_reference,
                    'txid'           => $tx->txid,
                ]);

                return response()->json([
                    'received'  => true,
                    'updated'   => true,
                    'status'    => 'paga',
                    'e2e_id'    => $tx->e2e_id,
                    'payer'     => [
                        'name'     => $tx->payer_name,
                        'document' => $tx->payer_document,
                    ],
                ]);
            }

            /** ==========================================================
             *  7) Demais status apenas são logados
             *  ========================================================== */
            Log::info('ℹ️ Webhook recebido com status não final', [
                'status' => $status,
                'external_ref' => $externalRef,
            ]);

            return response()->json([
                'received' => true,
                'ignored'  => true,
                'reason'   => 'status_not_approved',
            ]);

        } catch (\Throwable $e) {

            Log::error('❌ ERRO AO PROCESSAR WEBHOOK LUMNIS', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
