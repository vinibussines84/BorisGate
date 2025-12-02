<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Jobs\SendWebhookPixUpdateJob;
use App\Services\WalletService;

class LumnisWebhookController extends Controller
{
    public function __invoke(Request $request, WalletService $wallet)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | 1) Normaliza payload
            |--------------------------------------------------------------------------
            */
            $raw = $request->json()->all()
                ?: json_decode($request->getContent(), true)
                ?: [];

            Log::info("📩 Webhook Lumnis PIX recebido", ['payload' => $raw]);

            /*
            |--------------------------------------------------------------------------
            | 2) Extrai campos conhecidos da Lumnis
            |--------------------------------------------------------------------------
            */
            $externalRef = data_get($raw, 'external_ref')
                ?? data_get($raw, 'externalRef')
                ?? data_get($raw, 'external_reference');

            $txid   = data_get($raw, 'id') ?? data_get($raw, 'txid');
            $status = strtolower(data_get($raw, 'status', 'unknown'));

            if (!$externalRef && !$txid) {
                return response()->json(['error' => 'missing_reference'], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Busca transação com LOCK
            |--------------------------------------------------------------------------
            */
            $tx = Transaction::query()
                ->when($externalRef, fn($q) => $q->where('external_reference', $externalRef))
                ->when(!$externalRef && $txid, fn($q) => $q->where('txid', $txid))
                ->lockForUpdate()
                ->first();

            if (!$tx) {
                Log::warning("⚠️ TX não encontrada no webhook Lumnis", [
                    'externalRef' => $externalRef,
                    'txid'        => $txid,
                ]);
                return response()->json(['error' => 'Transaction not found'], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | 4) Idempotência
            |--------------------------------------------------------------------------
            */
            if (in_array($tx->status, [
                TransactionStatus::PAGA->value,
                TransactionStatus::FALHA->value
            ])) {
                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | 5) MAPA REAL DE STATUS (Lumnis → Sistema)
            |--------------------------------------------------------------------------
            */
            $map = [
                'approved'  => TransactionStatus::PAGA,
                'paid'      => TransactionStatus::PAGA,
                'confirmed' => TransactionStatus::PAGA,

                'pending'   => TransactionStatus::PENDENTE,
                'waiting'   => TransactionStatus::PENDENTE,

                'processing' => TransactionStatus::MED,

                'failed'     => TransactionStatus::FALHA,
                'error'      => TransactionStatus::FALHA,
                'denied'     => TransactionStatus::FALHA,
                'canceled'   => TransactionStatus::FALHA,
                'expired'    => TransactionStatus::FALHA,
            ];

            $newStatus = $map[$status] ?? null;

            if (!$newStatus) {
                return response()->json(['ignored' => true]);
            }

            $oldStatus = TransactionStatus::tryFrom($tx->status);

            /*
            |--------------------------------------------------------------------------
            | 6) Aplica lógica financeira no wallet
            |--------------------------------------------------------------------------
            */
            $wallet->applyStatusChange($tx, $oldStatus, $newStatus);

            /*
            |--------------------------------------------------------------------------
            | 7) Atualiza transação silenciosamente
            |--------------------------------------------------------------------------
            */
            $this->updateTransactionQuietly($tx, $newStatus, $raw);

            /*
            |--------------------------------------------------------------------------
            | 8) Dispara webhook IN para o cliente (somente quando PAGO)
            |--------------------------------------------------------------------------
            */
            if (
                $newStatus === TransactionStatus::PAGA &&
                $tx->user?->webhook_enabled &&
                $tx->user?->webhook_in_url
            ) {
                SendWebhookPixUpdateJob::dispatch($tx->id);
            }

            return response()->json([
                'success' => true,
                'status'  => $newStatus->value,
            ]);

        } catch (\Throwable $e) {

            Log::error("🚨 ERRO NO WEBHOOK LUMNIS PIX", [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }


    /**
     * Atualiza TX sem observer
     */
    private function updateTransactionQuietly(Transaction $tx, TransactionStatus $newStatus, array $raw)
    {
        $payerName     = data_get($raw, 'payer_name');
        $payerDocument = data_get($raw, 'payer_document');
        $endToEnd      = data_get($raw, 'endtoend');
        $providerId    = data_get($raw, 'identifier') ?? data_get($raw, 'id');

        $tx->updateQuietly([
            'status'                  => $newStatus->value,
            'paid_at'                 => now(),
            'provider_transaction_id' => $providerId,
            'payer_name'              => $payerName ?: $tx->payer_name,
            'payer_document'          => $payerDocument ?: $tx->payer_document,
            'e2e_id'                  => $endToEnd ?: $tx->e2e_id,
            'provider_payload'        => $raw,
        ]);
    }
}
