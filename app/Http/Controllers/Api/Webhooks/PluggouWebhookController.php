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

class PluggouWebhookController extends Controller
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

            Log::info("📩 Webhook Pluggou PIX recebido", ['payload' => $raw]);

            /*
            |--------------------------------------------------------------------------
            | 2) Extrai campos conhecidos da Pluggou
            |--------------------------------------------------------------------------
            */
            $providerId  = data_get($raw, 'data.id');           // ID da transação Pluggou
            $status      = strtolower(data_get($raw, 'data.status', 'unknown'));
            $e2e         = data_get($raw, 'data.e2e_id');
            $paidAt      = data_get($raw, 'data.paid_at');
            $externalRef = data_get($raw, 'data.external_id'); // caso você futuramente adicione

            if (!$providerId) {
                return response()->json(['error' => 'missing_reference'], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Busca transação com LOCK
            |--------------------------------------------------------------------------
            */
            $tx = Transaction::query()
                ->where('provider_transaction_id', $providerId)
                ->lockForUpdate()
                ->first();

            if (!$tx) {
                Log::warning("⚠️ TX não encontrada no webhook Pluggou", [
                    'provider_transaction_id' => $providerId,
                ]);
                return response()->json(['error' => 'Transaction not found'], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | 4) Idempotência — se já está PAGA ou FALHA, ignorar
            |--------------------------------------------------------------------------
            */
            if (in_array($tx->status, [
                TransactionStatus::PAGA->value,
                TransactionStatus::FALHA->value
            ])) {
                Log::info("⏳ Webhook Pluggou ignorado — status final já aplicado", [
                    'id'     => $tx->id,
                    'status' => $tx->status,
                ]);

                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | 5) MAPA REAL DE STATUS (Pluggou → Sistema)
            |--------------------------------------------------------------------------
            */
            $map = [
                'paid'      => TransactionStatus::PAGA,
                'approved'  => TransactionStatus::PAGA,

                'waiting'   => TransactionStatus::PENDENTE,
                'pending'   => TransactionStatus::PENDENTE,

                'processing' => TransactionStatus::MED,

                'failed'   => TransactionStatus::FALHA,
                'error'    => TransactionStatus::FALHA,
                'denied'   => TransactionStatus::FALHA,
                'canceled' => TransactionStatus::FALHA,
                'expired'  => TransactionStatus::FALHA,
            ];

            $newStatus = $map[$status] ?? null;

            if (!$newStatus) {
                Log::info("ℹ️ Webhook Pluggou ignorado — status não mapeado", [
                    'status' => $status
                ]);
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
            | 7) Atualiza TX silenciosamente
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

            Log::error("🚨 ERRO NO WEBHOOK PLUGGOU PIX", [
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
        $payerName     = data_get($raw, 'data.payer_name');
        $payerDocument = data_get($raw, 'data.payer_document');
        $e2e           = data_get($raw, 'data.e2e_id');
        $providerId    = data_get($raw, 'data.id');

        $tx->updateQuietly([
            'status'                  => $newStatus->value,
            'paid_at'                 => data_get($raw, 'data.paid_at') ?? now(),
            'provider_transaction_id' => $providerId,
            'payer_name'              => $payerName ?: $tx->payer_name,
            'payer_document'          => $payerDocument ?: $tx->payer_document,
            'e2e_id'                  => $e2e ?: $tx->e2e_id,
            'provider_payload'        => $raw,
        ]);
    }
}
