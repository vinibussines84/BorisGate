<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Transaction;
use App\Enums\TransactionStatus;

class PodPayWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            // 1️⃣ Normaliza payload
            $raw = $request->json()->all();
            if (!$raw) {
                $raw = json_decode($request->getContent(), true) ?? [];
            }

            Log::info("📩 Webhook PodPay recebido", $raw);

            $data = data_get($raw, 'data', []);

            $externalRef = data_get($data, 'externalRef');
            $txid        = data_get($data, 'id');
            $status      = strtoupper(data_get($data, 'status'));

            if (!$externalRef && !$txid) {
                return response()->json(['error' => 'Missing externalRef or id'], 422);
            }

            // 2️⃣ Localiza transação
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

            // 3️⃣ Idempotência
            if ($tx->isPaga()) {
                return response()->json([
                    'received' => true,
                    'ignored'  => true,
                    'reason'   => 'already_paid',
                ]);
            }

            // 4️⃣ Extrai payer info mínimos
            $endToEnd = data_get($data, 'pix.end2EndId');

            // 5️⃣ Verifica status final
            if (in_array($status, ["PAID", "APPROVED", "CONFIRMED"])) {

                // 5.1 Limpa provider_payload => somente campos permitidos
                $cleanPayload = [
                    "id"            => data_get($data, 'id'),
                    "type"          => data_get($data, 'type', 'transaction'),
                    "paymentMethod" => data_get($data, 'paymentMethod'),
                    "status"        => data_get($data, 'status'),
                    "paidAt"        => data_get($data, 'paidAt'),
                    "paidAmount"    => data_get($data, 'paidAmount'),
                    "pix" => [
                        "qrcode"    => data_get($data, 'pix.qrcode'),
                        "end2EndId" => data_get($data, 'pix.end2EndId'),
                    ]
                ];

                // 5.2 Atualiza transação local
                $tx->update([
                    'status'                 => TransactionStatus::PAGA->value,
                    'paid_at'                => now(),
                    'e2e_id'                 => $endToEnd ?: $tx->e2e_id,
                    'provider_transaction_id'=> $txid,
                    'provider_payload'       => $cleanPayload,
                ]);

                Log::info("✅ PodPay: transação marcada como PAGA!", [
                    'transaction_id' => $tx->id,
                    'externalRef'    => $tx->external_reference,
                ]);

                // 6️⃣ ENVIA WEBHOOK PARA O CLIENTE (igual Lumnis — Pix Update)
                if ($tx->user->webhook_enabled && $tx->user->webhook_in_url) {
                    try {
                        Http::post($tx->user->webhook_in_url, [
                            "type"            => "Pix Update",
                            "event"           => "updated",
                            "transaction_id"  => $tx->id,
                            "external_id"     => $tx->external_reference,
                            "user"            => $tx->user->name,
                            "amount"          => number_format($tx->amount, 2, '.', ''),
                            "fee"             => number_format($tx->fee, 2, '.', ''),
                            "currency"        => $tx->currency,
                            "status"          => "paga",
                            "txid"            => $tx->txid,
                            "e2e"             => $tx->e2e_id,
                            "direction"       => $tx->direction,
                            "method"          => $tx->method,
                            "created_at"      => $tx->created_at,
                            "updated_at"      => $tx->updated_at,
                            "paid_at"         => $tx->paid_at,
                            "canceled_at"     => $tx->canceled_at,
                            "provider_payload"=> $cleanPayload
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("⚠️ Falha ao enviar webhook ao cliente", [
                            'tx_id' => $tx->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                return response()->json([
                    'received' => true,
                    'updated'  => true,
                    'status'   => 'paga',
                ]);
            }

            // 7️⃣ Status não final (IGNORA)
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
