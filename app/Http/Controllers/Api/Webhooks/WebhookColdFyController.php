<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Support\StatusMap;
use App\Services\WalletService;
use App\Jobs\SendWebhookPixUpdateJob;
use Illuminate\Support\Str;

class WebhookColdFyController extends Controller
{
    public function handle(Request $request, WalletService $wallet)
    {
        if (!$request->isJson()) {
            return response()->json([
                'error' => 'Invalid content type. JSON expected.'
            ], 415);
        }

        $payload = $request->json()->all();

        Log::channel('webhooks')->info('📥 Webhook COLDFY recebido', [
            'payload' => $payload
        ]);

        try {
            /*
            |--------------------------------------------------------------------------
            | 🔍 Localizar transação (por objectId → provider_transaction_id)
            |--------------------------------------------------------------------------
            */
            $objectId   = data_get($payload, 'objectId');
            $status     = data_get($payload, 'data.status');
            $amount     = ((float) data_get($payload, 'data.amount')) / 100; // ✅ converte centavos → reais

            if (!$objectId) {
                return response()->json(['error' => 'missing_objectId'], 422);
            }

            $tx = Transaction::where('provider_transaction_id', $objectId)
                ->lockForUpdate()
                ->first();

            if (!$tx) {
                return response()->json(['error' => 'transaction_not_found'], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | 🚫 Ignorar se valor muito alto (exemplo de segurança)
            |--------------------------------------------------------------------------
            */
            if ($amount > 4000) {
                Log::channel('webhooks')->warning('🚨 Webhook ColdFy ignorado — amount acima do limite de 4000', [
                    'transaction_id' => $tx->id,
                    'amount'         => $amount,
                ]);

                return response()->json([
                    'ignored' => true,
                    'reason'  => 'amount_above_limit_4000'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 🚫 Ignorar se valor acima de R$300,00 (mantém pendente)
            |--------------------------------------------------------------------------
            */
            if ($amount > 300.00) {
                Log::channel('webhooks')->info('⏸️ Webhook ColdFy ignorado — valor acima de R$300,00 mantido pendente', [
                    'transaction_id' => $tx->id,
                    'amount'         => $amount,
                    'status_atual'   => $tx->status,
                ]);

                return response()->json([
                    'ignored' => true,
                    'reason'  => 'amount_above_300_pending'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ⚙️ Normalizar status e converter para Enum
            |--------------------------------------------------------------------------
            */
            $normalized = StatusMap::normalize($status);
            $newEnum    = TransactionStatus::fromLoose($normalized);
            $oldEnum    = TransactionStatus::tryFrom($tx->status);

            /*
            |--------------------------------------------------------------------------
            | Atualizar saldo apenas se houve mudança de status
            |--------------------------------------------------------------------------
            */
            $wallet->applyStatusChange($tx, $oldEnum, $newEnum);

            /*
            |--------------------------------------------------------------------------
            | 🧾 Gerar E2E se pago e ausente
            |--------------------------------------------------------------------------
            */
            $incomingE2E = data_get($payload, 'data.pix.end2EndId');

            if ($newEnum === TransactionStatus::PAID && empty($incomingE2E)) {
                $incomingE2E = $this->generateFallbackE2E($tx);

                Log::channel('webhooks')->warning('⚠️ Gerado E2E interno (faltante no webhook COLDFY)', [
                    'transaction_id' => $tx->id,
                    'generated_e2e'  => $incomingE2E,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ⏱️ Definir paid_at (usando data enviada ou fallback)
            |--------------------------------------------------------------------------
            */
            $paidAt = data_get($payload, 'data.paidAt') ?? $tx->paid_at;

            /*
            |--------------------------------------------------------------------------
            | 💾 Atualizar transação local
            |--------------------------------------------------------------------------
            */
            $tx->updateQuietly([
                'status'           => $newEnum->value,
                'e2e_id'           => $incomingE2E,
                'paid_at'          => $paidAt,
                'provider_payload' => $payload,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 🔔 Disparar webhook do cliente (apenas se pago)
            |--------------------------------------------------------------------------
            */
            if (
                $newEnum === TransactionStatus::PAID &&
                $tx->user?->webhook_enabled &&
                $tx->user?->webhook_in_url
            ) {
                SendWebhookPixUpdateJob::dispatch($tx->id)->onQueue('webhooks');
            }

            Log::channel('webhooks')->info("✅ COLDFY webhook processado com sucesso", [
                'transaction_id' => $tx->id,
                'provider_id'    => $objectId,
                'amount'         => $amount,
                'status'         => $newEnum->value,
                'paid_at'        => $paidAt,
            ]);

            return response()->json([
                'success' => true,
                'status'  => $newEnum->value,
                'e2e_id'  => $incomingE2E,
            ]);

        } catch (\Throwable $e) {
            Log::channel('webhooks')->error("🚨 ERRO WEBHOOK COLDFY", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'internal_server_error'
            ], 500);
        }
    }

    /**
     * 🔐 Gera E2E interno quando o provedor não enviar
     */
    private function generateFallbackE2E(Transaction $tx): string
    {
        $timestamp = now('UTC')->format('YmdHis');
        $random    = strtoupper(Str::random(6));
        $userPart  = str_pad((string) ($tx->user_id ?? 0), 3, '0', STR_PAD_LEFT);
        $txPart    = str_pad((string) $tx->id, 4, '0', STR_PAD_LEFT);

        return "E2E{$timestamp}{$userPart}{$txPart}{$random}";
    }
}
