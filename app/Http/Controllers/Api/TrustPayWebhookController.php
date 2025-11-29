<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Transaction;
use App\Enums\TransactionStatus;

class TrustPayWebhookController extends Controller
{
    /**
     * Webhook de confirmação de PAYIN do TrustPay (legacy).
     * Espera campos como:
     * - externalId / external_id
     * - status (ex: paid, canceled)
     * - type (ex: PAYIN_CONFIRMED)
     * - uuid (às vezes o txid do provedor)
     * - endToEndId / metadata.endToEnd
     * - amount (opcional, conferência)
     * - eventId / id (para idempotência)
     */
    public function handle(Request $request)
    {
        $data = $request->all();
        Log::info('📩 TrustPay webhook recebido', ['payload' => $data]);

        // ---------- Campos do payload ----------
        $eventId = (string) (data_get($data, 'eventId') ?? data_get($data, 'id') ?? '');
        $type    = Str::upper((string) data_get($data, 'type', ''));
        $status  = Str::lower((string) data_get($data, 'status', 'pending'));

        $extId   = (string) (data_get($data, 'externalId') ?? data_get($data, 'external_id'));
        $uuid    = (string) (data_get($data, 'uuid') ?? data_get($data, 'metadata.authCode') ?? '');
        $e2e     = (string) (data_get($data, 'endToEndId') ?? data_get($data, 'metadata.endToEnd') ?? data_get($data, 'end_to_end') ?? '');

        $txidIn  = (string) (data_get($data, 'txid') ?? data_get($data, 'txId') ?? '');
        $provIn  = (string) (data_get($data, 'provider_transaction_id') ?? data_get($data, 'providerId') ?? '');

        $amount  = data_get($data, 'amount'); // opcional (para conferência)

        if (!$extId && !$uuid) {
            Log::warning('⚠️ TrustPay webhook sem externalId/uuid', ['payload' => $data]);
            return response()->json(['error' => 'Missing externalId/uuid'], 422);
        }

        // ---------- Localiza transação ----------
        $tx = Transaction::query()
            ->when($extId, fn ($q) => $q->where('external_reference', $extId))
            ->when(!$extId && $uuid, fn ($q) => $q->where(function ($w) use ($uuid) {
                $w->where('txid', $uuid)->orWhere('provider_transaction_id', $uuid);
            }))
            ->first();

        if (!$tx) {
            Log::warning('⚠️ Transação não encontrada para webhook TrustPay', [
                'external_id' => $extId,
                'uuid'        => $uuid,
            ]);
            return response()->json(['error' => 'Transação não encontrada.'], 404);
        }

        // ---------- Idempotência ----------
        // Se já processamos este eventId, apenas confirme.
        $pp = is_array($tx->provider_payload) ? $tx->provider_payload : [];
        $already = isset($pp['webhook_ids']) && is_array($pp['webhook_ids']) && $eventId && in_array($eventId, $pp['webhook_ids'], true);
        if ($already) {
            Log::info('ℹ️ Webhook idempotente ignorado (já processado)', ['tx_id' => $tx->id, 'event_id' => $eventId]);
            return response()->json(['success' => true, 'idempotent' => true]);
        }

        // ---------- Atualizações livres ----------
        if ($e2e) {
            $tx->e2e_id = $e2e;
        }
        if ($txidIn) {
            $tx->txid = substr(preg_replace('/[^A-Za-z0-9\-._]/', '', $txidIn), 0, 64);
        } elseif ($uuid && empty($tx->txid)) {
            // use uuid como fallback de txid se vier vazio
            $tx->txid = substr(preg_replace('/[^A-Za-z0-9\-._]/', '', $uuid), 0, 64);
        }
        if ($provIn) {
            $tx->provider_transaction_id = substr(preg_replace('/[^A-Za-z0-9\-._]/', '', $provIn), 0, 100);
        }

        // ---------- Conferência opcional de valor ----------
        if (is_numeric($amount)) {
            // mantém última conferência salva no payload
            $pp['webhook_amount_last'] = (float) $amount;
        }

        // ---------- Mapeamento de status ----------
        // Regras: marcar como paga quando type = PAYIN_CONFIRMED OU status = paid
        $toPaid = ($type === 'PAYIN_CONFIRMED') || ($status === 'paid');
        $toCanceled = in_array($status, ['canceled', 'cancelled', 'failed', 'rejected'], true);

        if ($toPaid) {
            $tx->status  = TransactionStatus::PAGA; // 'paga'
            $tx->paid_at = $tx->paid_at ?: now();
        } elseif ($toCanceled) {
            $tx->status = TransactionStatus::CANCELADA; // ajuste conforme seu Enum
        } // senão, deixa como está (pendente/em processamento)

        // ---------- Auditoria do webhook ----------
        $pp['webhook_last'] = $data;
        $pp['webhook_last_received_at'] = now()->toIso8601String();
        if ($eventId) {
            $pp['webhook_ids'] = array_values(array_unique(array_merge($pp['webhook_ids'] ?? [], [$eventId])));
        }
        $tx->provider_payload = $pp;

        $tx->save();
        $tx->refresh();

        Log::info('✅ Transação atualizada (TrustPay)', [
            'id'          => $tx->id,
            'external_id' => $tx->external_reference,
            'status'      => $tx->status instanceof TransactionStatus ? $tx->status->value : (string) $tx->status,
            'e2e_id'      => $tx->e2e_id,
            'txid'        => $tx->txid,
            'provider_id' => $tx->provider_transaction_id,
        ]);

        return response()->json(['success' => true]);
    }
}
