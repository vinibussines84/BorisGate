<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;

class PodPayWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            /** ================================================
             * 1) Normaliza payload
             * ================================================ */
            $data = $request->json()->all();
            if (!$data) {
                $data = json_decode($request->getContent(), true) ?? [];
            }

            Log::info("📩 Webhook PodPay recebido", $data);

            /** ================================================
             * 2) Captura external_id e txid
             * ================================================ */
            $externalRef = data_get($data, "data.externalRef");
            $txid        = data_get($data, "data.id");
            $status      = strtoupper(data_get($data, "data.status"));

            if (!$externalRef && !$txid) {
                return response()->json(['error' => 'Missing externalRef or id'], 422);
            }

            /** ================================================
             * 3) Localiza transação
             * ================================================ */
            $tx = Transaction::query()
                ->when($externalRef, fn($q) => $q->where('external_reference', $externalRef))
                ->when(!$externalRef && $txid, fn($q) => $q->where('txid', $txid))
                ->first();

            if (!$tx) {
                Log::warning("⚠️ TX não encontrada (PodPay)", [
                    'externalRef' => $externalRef,
                    'txid'        => $txid
                ]);

                return response()->json(['error' => 'Transaction not found'], 404);
            }

            /** ================================================
             * 4) Idempotência — se já estiver paga, ignora
             * ================================================ */
            if ($tx->isPaga()) {
                return response()->json([
                    'received' => true,
                    'ignored'  => true,
                    'reason'   => 'already_paid',
                ]);
            }

            /** ================================================
             * 5) Extrai payer info
             * ================================================ */
            $payerName     = data_get($data, "data.customer.name");
            $payerDocument = data_get($data, "data.customer.document.number");
            $endToEnd      = data_get($data, "data.pix.end2EndId");

            /** ================================================
             * 6) Atualiza somente se status = PAID
             * ================================================ */
            if (in_array($status, ["PAID", "APPROVED", "CONFIRMED"])) {

                $tx->update([
                    'status'                 => TransactionStatus::PAGA->value,
                    'paid_at'                => now(),
                    'e2e_id'                 => $endToEnd ?: $tx->e2e_id,
                    'payer_name'             => $payerName ?: $tx->payer_name,
                    'payer_document'         => $payerDocument ?: $tx->payer_document,
                    'provider_transaction_id'=> $txid,
                    'provider_payload'       => $data,
                ]);

                Log::info("✅ PodPay: transação marcada como PAGA!", [
                    'transaction_id' => $tx->id,
                    'externalRef'    => $tx->external_reference,
                    'txid'           => $tx->txid
                ]);

                /** ================================================
                 * 7) Envia webhook AO CLIENTE (igual Lumnis)
                 * ================================================ */
                if ($tx->user->webhook_enabled && $tx->user->webhook_in_url) {
                    try {
                        Http::post($tx->user->webhook_in_url, [
                            'type'            => 'Pix Paid',
                            'event'           => 'paid',
                            'transaction_id'  => $tx->id,
                            'external_id'     => $tx->external_reference,
                            'user'            => $tx->user->name,
                            'amount'          => number_format($tx->amount, 2, '.', ''),
                            'fee'             => number_format($tx->fee, 2, '.', ''),
                            'currency'        => 'BRL',
                            'status'          => 'paga',
                            'txid'            => $tx->txid,
                            'e2e'             => $tx->e2e_id,
                            'payer_name'      => $tx->payer_name,
                            'payer_document'  => $tx->payer_document,
                            'direction'       => $tx->direction,
                            'method'          => $tx->method,
                            'created_at'      => $tx->created_at,
                            'updated_at'      => $tx->updated_at,
                            'paid_at'         => $tx->paid_at,
                            'provider_payload'=> $data
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("⚠️ Falha ao enviar webhook ao cliente (PodPay)", [
                            'tx_id' => $tx->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                return response()->json([
                    'received' => true,
                    'updated'  => true,
                    'status'   => 'paga',
                ]);
            }

            /** ================================================
             * 8) Caso não seja status final
             * ================================================ */
            Log::info("ℹ️ Webhook PodPay ignorado — status não final", [
                'status' => $status
            ]);

            return response()->json([
                'received' => true,
                'ignored'  => true,
                'reason'   => 'status_not_final'
            ]);

        } catch (\Throwable $e) {

            Log::error("❌ ERRO AO PROCESSAR WEBHOOK PODPAY", [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
