<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Jobs\SendWebhookPixUpdateJob;
use App\Services\WalletService;

class WebhookCoffePayController extends Controller
{
    public function handle(Request $request, WalletService $wallet)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | 1. Captura e normaliza payload
            |--------------------------------------------------------------------------
            */
            $data = $request->json()->all();

            Log::info("📩 COFFE PAY WEBHOOK RECEIVED", [
                'payload' => $data,
                'headers' => $request->headers->all()
            ]);

            $providerId = data_get($data, 'id');
            $txId       = data_get($data, 'txId');
            $externalId = data_get($data, 'external_id');
            $statusRaw  = strtolower(data_get($data, 'status', 'unknown'));
            $amount     = data_get($data, 'amount');
            $e2e        = data_get($data, 'pix.end_to_end');

            if (!$providerId && !$txId && !$externalId) {
                Log::warning("❌ CoffePay webhook chegou sem referência");
                return response()->json(['error' => 'missing_reference'], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Localiza transação no banco
            |--------------------------------------------------------------------------
            */
            $tx = Transaction::query()
                ->when($externalId, fn($q) => $q->where('external_reference', $externalId))
                ->when(!$externalId && $providerId, fn($q) => $q->where('provider_transaction_id', $providerId))
                ->when(!$externalId && !$providerId && $txId, fn($q) => $q->where('txid', $txId))
                ->lockForUpdate()
                ->first();

            if (!$tx) {
                Log::warning("⚠️ TX NÃO ENCONTRADA (CoffePay)", [
                    'providerId' => $providerId,
                    'txId'       => $txId,
                    'externalId' => $externalId
                ]);
                return response()->json(['error' => 'transaction_not_found'], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Idempotência
            |--------------------------------------------------------------------------
            */
            if (in_array($tx->status, [
                TransactionStatus::PAGA->value,
                TransactionStatus::FALHA->value
            ])) {
                Log::info("ℹ️ Ignorando webhook CoffePay — transação já finalizada", [
                    'tx_id' => $tx->id,
                    'status' => $tx->status
                ]);
                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Mapeamento de status COFFE PAY → Sistema
            |--------------------------------------------------------------------------
            */
            $map = [
                'paid'       => TransactionStatus::PAGA,
                'completed'  => TransactionStatus::PAGA,

                'pending'    => TransactionStatus::PENDENTE,
                'created'    => TransactionStatus::PENDENTE,

                'processing' => TransactionStatus::MED,

                'failed'     => TransactionStatus::FALHA,
                'canceled'   => TransactionStatus::FALHA,
                'cancelled'  => TransactionStatus::FALHA,
                'error'      => TransactionStatus::FALHA,
                'expired'    => TransactionStatus::FALHA,
                'refused'    => TransactionStatus::FALHA,
                'rejected'   => TransactionStatus::FALHA,
            ];

            $newStatus = $map[$statusRaw] ?? null;

            if (!$newStatus) {
                Log::info("ℹ️ CoffePay webhook ignorado — status não mapeado", [
                    'status' => $statusRaw
                ]);
                return response()->json(['ignored' => true]);
            }

            $oldStatus = TransactionStatus::tryFrom($tx->status);

            /*
            |--------------------------------------------------------------------------
            | 5. Lógica financeira (creditar saldo)
            |--------------------------------------------------------------------------
            */
            $wallet->applyStatusChange($tx, $oldStatus, $newStatus);

            /*
            |--------------------------------------------------------------------------
            | 6. Atualiza transação no banco
            |--------------------------------------------------------------------------
            */
            $tx->updateQuietly([
                'status'                  => $newStatus->value,
                'paid_at'                 => data_get($data, 'paid_at') ?? $tx->paid_at,
                'provider_transaction_id' => $providerId ?? $txId,
                'e2e_id'                  => $e2e,
                'amount'                  => $amount ?? $tx->amount,
                'provider_payload'        => $data,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 7. Disparar webhook interno apenas quando pago
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
                'status'  => $newStatus->value
            ]);

        } catch (\Throwable $e) {

            Log::error("🚨 ERRO NO WEBHOOK COFFE PAY", [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}