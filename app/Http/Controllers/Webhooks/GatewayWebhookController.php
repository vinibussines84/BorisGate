<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Cobranca;
use App\Enums\TransactionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GatewayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $raw  = $request->getContent();
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            Log::warning('[GatewayWebhook] payload não-JSON', [
                'content_type' => $request->header('Content-Type'),
                'raw'          => Str::limit($raw ?? '', 2000),
            ]);
            return response()->json(['received' => false, 'error' => 'invalid_json'], 400);
        }

        $data       = $this->extractPayload($json);
        $statusRaw  = (string)($data['status'] ?? '');
        $statusNorm = $this->normalizeGatewayStatus($statusRaw);

        Log::info('[GatewayWebhook] recebido', [
            'ip'          => $request->ip(),
            'ua'          => $request->userAgent(),
            'signature'   => $request->header('X-Signature') ?? null,
            'idempotency' => $request->header('Idempotency-Key') ?? null,
            'content_md5' => md5($raw ?? ''),
            'summary'     => [
                'external_id'    => $data['external_id'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'txid'           => $data['txid'] ?? null,
                'status_raw'     => $statusRaw,
                'status_norm'    => $statusNorm,
                'paid_at'        => $data['paid_at'] ?? null,
            ],
            'payload'     => $json,
        ]);

        // se não for evento de pagamento concluído, apenas confirma
        if ($statusNorm !== 'paid') {
            return response()->json(['received' => true, 'ignored' => true, 'reason' => 'not_paid'], 200);
        }

        // campos principais
        $externalId   = (string)($data['external_id']    ?? '');
        $providerTxId = (string)($data['transaction_id'] ?? '');
        $paidAtRaw    = $data['paid_at'] ?? null;
        $endToEnd     = $this->extractEndToEnd($json) ?: ($data['txid'] ?? null);

        // tenta parsear paid_at
        $paidAt = null;
        if ($paidAtRaw) {
            try { $paidAt = CarbonImmutable::parse((string)$paidAtRaw); }
            catch (\Throwable) { $paidAt = now(); }
        } else {
            $paidAt = now();
        }

        DB::transaction(function () use ($externalId, $providerTxId, $endToEnd, $paidAt, $json) {

            $trx = $this->findTransaction($externalId, $providerTxId);
            if (!$trx) {
                Log::warning('[GatewayWebhook] Transaction não encontrada', [
                    'external_id'    => $externalId,
                    'transaction_id' => $providerTxId,
                ]);
                return;
            }

            // se já paga, ignora
            if ($trx->isPaga()) {
                return;
            }

            // marca como paga
            $trx->fill([
                'status'           => TransactionStatus::PAGA,
                'paid_at'          => $paidAt,
                'e2e_id'           => $endToEnd ?: $trx->e2e_id,
                'provider_payload' => $this->mergePayload((array)($trx->provider_payload ?? []), [
                    '_webhook_last' => now()->toIso8601String(),
                ]),
            ])->save();

            Log::info('[GatewayWebhook] Transaction marcada como PAGA', [
                'id'          => $trx->id,
                'external_id' => $trx->external_id,
                'provider_tx' => $trx->provider_transaction_id,
            ]);

            // ✅ também atualiza o status da cobrança vinculada
            $cobranca = null;
            if ($externalId) {
                $cobranca = Cobranca::where('external_id', $externalId)->first();
            }
            if (!$cobranca && $providerTxId) {
                $cobranca = Cobranca::where('provider_transaction_id', $providerTxId)->first();
            }

            if ($cobranca && strtolower((string)$cobranca->status) !== 'paid') {
                $cobranca->update(['status' => 'paid']);
                Log::info('[GatewayWebhook] Cobranca marcada como paid', [
                    'id'          => $cobranca->id,
                    'external_id' => $cobranca->external_id,
                ]);
            }
        });

        return response()->json(['received' => true, 'processed' => true, 'status' => 'paid'], 200);
    }

    protected function extractPayload(array $json): array
    {
        $cands = [$json, $json['data'] ?? null, $json['payload'] ?? null, $json['qrCodeResponse'] ?? null];

        foreach ($cands as $n) {
            if (!is_array($n)) continue;

            $externalId = data_get($n, 'external_id')
                ?? data_get($n, 'externalId')
                ?? data_get($n, 'metadata.external_id')
                ?? data_get($n, 'metadata.externalId');

            $providerTx = data_get($n, 'transaction_id')
                ?? data_get($n, 'transactionId')
                ?? data_get($n, 'id');

            $txid = data_get($n, 'txid')
                ?? data_get($n, 'pix.txid');

            $status = data_get($n, 'status')
                ?? data_get($n, 'payment_status')
                ?? data_get($n, 'pix.status');

            $paidAt = data_get($n, 'paid_at')
                ?? data_get($n, 'paidAt')
                ?? data_get($n, 'pix.paid_at')
                ?? data_get($n, 'pix.paidAt');

            if ($externalId || $providerTx || $txid || $status || $paidAt) {
                return [
                    'external_id'    => $externalId ? (string)$externalId : null,
                    'transaction_id' => $providerTx ? (string)$providerTx : null,
                    'txid'           => $txid ? (string)$txid : null,
                    'status'         => $status ? (string)$status : null,
                    'paid_at'        => $paidAt ? (string)$paidAt : null,
                ];
            }
        }

        return [
            'external_id'    => data_get($json, 'external_id') ?? data_get($json, 'externalId'),
            'transaction_id' => data_get($json, 'transaction_id') ?? data_get($json, 'transactionId') ?? data_get($json, 'id'),
            'txid'           => data_get($json, 'txid'),
            'status'         => data_get($json, 'status') ?? data_get($json, 'payment_status'),
            'paid_at'        => data_get($json, 'paid_at') ?? data_get($json, 'paidAt'),
        ];
    }

    protected function normalizeGatewayStatus(string $raw): string
    {
        return match (strtolower($raw)) {
            'paid', 'completed', 'success', 'approved', 'confirmed' => 'paid',
            'pending', 'waiting', ''                                => 'pending',
            'refunded'                                              => 'refunded',
            'canceled', 'cancelled'                                 => 'canceled',
            'failed', 'error', 'denied'                             => 'failed',
            default                                                 => 'pending',
        };
    }

    protected function extractEndToEnd(array $json): ?string
    {
        $cands = [
            data_get($json, 'endToEndId'),
            data_get($json, 'end_to_end'),
            data_get($json, 'e2e'),
            data_get($json, 'e2e_id'),
            data_get($json, 'data.endToEndId'),
            data_get($json, 'data.end_to_end'),
            data_get($json, 'pix.endToEndId'),
            data_get($json, 'pix.end_to_end'),
        ];
        foreach ($cands as $v) {
            if (is_string($v) && trim($v) !== '') return $v;
        }
        return null;
    }

    protected function findTransaction(?string $externalId, ?string $providerTxId): ?Transaction
    {
        if ($externalId && ($t = Transaction::where('external_id', $externalId)->first())) return $t;
        if ($providerTxId && ($t = Transaction::where('provider_transaction_id', $providerTxId)->first())) return $t;
        return null;
    }

    protected function mergePayload(array $old, array $incoming): array
    {
        $hist = $old['_history'] ?? [];
        $hist[] = ['at' => now()->toIso8601String(), 'data' => $incoming];
        $merged = array_replace_recursive($old, $incoming);
        $merged['_history'] = $hist;
        return $merged;
    }
}
