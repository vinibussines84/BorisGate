<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Transaction;
use App\Enums\TransactionStatus;

class VeltraxWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('📩 Veltrax webhook recebido', $request->all());
        $data = $request->all();

        // -------- lê status / ids em todos os níveis conhecidos (raiz, summary, payload, payload.summary, payload.payload)
        $status = Str::upper(
            data_get($data, 'status')
            ?? data_get($data, 'summary.status')
            ?? data_get($data, 'payload.status')
            ?? data_get($data, 'payload.summary.status')
            ?? data_get($data, 'payload.payload.status')
            ?? 'PENDING'
        );

        $externalId = data_get($data, 'external_id')
            ?? data_get($data, 'summary.external_id')
            ?? data_get($data, 'payload.external_id')
            ?? data_get($data, 'payload.summary.external_id')
            ?? data_get($data, 'payload.payload.external_id');

        $transactionId = data_get($data, 'transaction_id')
            ?? data_get($data, 'payload.transaction_id')
            ?? data_get($data, 'summary.transaction')
            ?? data_get($data, 'payload.summary.transaction')
            ?? data_get($data, 'payload.payload.transaction_id');

        $e2e = data_get($data, 'endToEndId')
            ?? data_get($data, 'end_to_end')
            ?? data_get($data, 'payload.endToEndId')
            ?? data_get($data, 'payload.end_to_end')
            ?? data_get($data, 'payload.payload.endToEndId')
            ?? data_get($data, 'payload.payload.end_to_end');

        // -------- localiza transação (prioriza external_reference; fallback por txid)
        $tx = Transaction::query()
            ->when($externalId, fn ($q) => $q->where('external_reference', $externalId))
            ->when(!$externalId && $transactionId, fn ($q) => $q->where(function ($qq) use ($transactionId) {
                $qq->where('txid', $transactionId)
                   ->orWhere('provider_transaction_id', $transactionId);
            }))
            ->first();

        if (!$tx) {
            Log::warning('⚠️ Transação não encontrada para webhook Veltrax', [
                'external_id'    => $externalId,
                'transaction_id' => $transactionId,
            ]);
            return response()->json(['error' => 'Transação não encontrada.'], 404);
        }

        // -------- atualiza E2E (se vier)
        if ($e2e) {
            $tx->e2e_id = $e2e;
        }

        // -------- marca paga SOMENTE quando COMPLETED
        if ($status === 'COMPLETED') {
            $tx->status  = TransactionStatus::PAGA; // case PAGA = 'paga'
            $tx->paid_at = $tx->paid_at ?: now();
        }

        // guarda último webhook (opcional)
        $pp = is_array($tx->provider_payload) ? $tx->provider_payload : [];
        $pp['webhook_last'] = $data;
        $pp['webhook_last_received_at'] = now()->toIso8601String();
        $tx->provider_payload = $pp;

        $tx->save();
        $tx->refresh();

        Log::info('✅ Transação atualizada (Veltrax)', [
            'id'          => $tx->id,
            'external_id' => $tx->external_reference,
            'status'      => $tx->status instanceof TransactionStatus ? $tx->status->value : (string) $tx->status,
            'e2e_id'      => $tx->e2e_id,
        ]);

        // -------- dispara webhook mínimo para o cliente
        $this->dispatchCashinUpdated($tx);

        return response()->json(['success' => true]);
    }

    private function dispatchCashinUpdated(Transaction $tx): void
    {
        try {
            $user = $tx->user;
            if (!$user || !$user->webhook_enabled || empty($user->webhook_in_url)) return;

            $url = $user->webhook_in_url;
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Log::warning('cashin.updated: URL inválida', ['user_id' => $user->id, 'url' => $url]);
                return;
            }

            $tz  = config('app.timezone', 'UTC');
            $fmt = fn($dt) => optional($dt)?->copy()?->timezone($tz)?->format('d/m/Y \à\s H:i\h');

            $payload = [
                'event'       => 'cashin.updated',
                'version'     => '1.0',
                'occurred_at' => $fmt($tx->updated_at) ?? $fmt(now()),
                'data' => [
                    'id'             => (string) $tx->id,
                    'external_id'    => (string) $tx->external_reference,
                    'current_status' => $tx->status instanceof TransactionStatus ? $tx->status->value : (string) $tx->status,
                    'status_label'   => $tx->status_label,
                    'amount'         => (float) $tx->amount,
                    'currency'       => $tx->currency,
                    'txid'           => $tx->txid,
                    'e2e_id'         => $tx->e2e_id,
                    'created_at'     => $fmt($tx->created_at),
                    'updated_at'     => $fmt($tx->updated_at),
                ],
            ];

            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $ts   = (string) now('UTC')->timestamp;
            $sig  = hash_hmac('sha256', $ts . '.' . $body, (string) $user->secretkey);

            $resp = Http::timeout(6)
                ->retry(2, 300)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Event'      => 'cashin.updated',
                    'X-User-Id'    => (string) $user->id,
                    'X-Timestamp'  => $ts,
                    'X-Signature'  => $sig,
                    'X-Timezone'   => $tz,
                ])
                ->post($url, $payload);

            if (!$resp->successful()) {
                Log::warning('Webhook cashin.updated falhou', [
                    'user_id' => $user->id,
                    'url'     => $url,
                    'status'  => $resp->status(),
                    'body'    => $resp->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar webhook cashin.updated', [
                'tx_id'   => $tx->id ?? null,
                'user_id' => $tx->user_id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
