<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Services\WalletService;
use App\Jobs\SendWebhookPixUpdateJob;
use Illuminate\Support\Str;

class WebhookXFlowController extends Controller
{
    public function handle(Request $request, WalletService $wallet)
    {
        if (!$request->isJson()) {
            return response()->json([
                'error' => 'Invalid content type. JSON expected.'
            ], 415);
        }

        $payload = $request->json()->all();

        Log::channel('webhooks')->info('📥 Webhook XFlow recebido', [
            'payload' => $payload
        ]);

        try {

            /*
            |--------------------------------------------------------------------------
            | IDENTIFICAR TRANSACAO PELO transaction_id (txid)
            |--------------------------------------------------------------------------
            */
            $providerTxId = data_get($payload, 'transaction_id');

            if (!$providerTxId) {
                return response()->json(['error' => 'missing_transaction_id'], 422);
            }

            $tx = Transaction::where('provider_transaction_id', $providerTxId)
                ->lockForUpdate()
                ->first();

            if (!$tx) {
                Log::channel('webhooks')->warning('⚠ Transação não encontrada', [
                    'transaction_id' => $providerTxId,
                ]);
                return response()->json(['error' => 'transaction_not_found'], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | IGNORAR SE JÁ FINALIZADA
            |--------------------------------------------------------------------------
            */
            if (in_array($tx->status, ['PAID', 'FAILED'], true)) {

                Log::channel('webhooks')->info("⚠ Webhook XFlow ignorado — transação já finalizada", [
                    'transaction_id' => $tx->id,
                    'status'         => $tx->status
                ]);

                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | VERIFICAR STATUS RECEBIDO
            |--------------------------------------------------------------------------
            */
            $incomingStatus = strtoupper(data_get($payload, 'status'));

            if ($incomingStatus !== 'COMPLETED') {
                Log::channel('webhooks')->info("ℹ Status ignorado (não é COMPLETED)", [
                    'incoming' => $incomingStatus,
                ]);

                return response()->json(['ignored' => true]);
            }

            $newEnum = TransactionStatus::PAID;
            $oldEnum = TransactionStatus::tryFrom($tx->status);

            /*
            |--------------------------------------------------------------------------
            | APLICAR LÓGICA DE SALDO (com idempotência)
            |--------------------------------------------------------------------------
            |
            | NUNCA calcular net manualmente.
            | O WalletService já cuida de:
            | - taxa fixa/percentual do usuário
            | - idempotência reforçada
            | - crédito em amount_available
            |--------------------------------------------------------------------------
            */
            $wallet->applyStatusChange($tx, $oldEnum, $newEnum);

            /*
            |--------------------------------------------------------------------------
            | GERAR E2E SE AUSENTE
            |--------------------------------------------------------------------------
            */
            $incomingE2E = data_get($payload, 'e2e_id');

            if (empty($incomingE2E)) {
                $incomingE2E = $this->generateFallbackE2E($tx);

                Log::channel('webhooks')->warning('⚠ E2E ausente — gerado internamente', [
                    'transaction_id' => $tx->id,
                    'generated_e2e'  => $incomingE2E,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | paid_at
            |--------------------------------------------------------------------------
            */
            $paidAt = now()->toDateTimeString();

            /*
            |--------------------------------------------------------------------------
            | ATUALIZAR TRANSACAO
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
            | DISPARAR WEBHOOK EXTERNO DO CLIENTE
            |--------------------------------------------------------------------------
            */
            if (
                $tx->user?->webhook_enabled &&
                $tx->user?->webhook_in_url
            ) {
                SendWebhookPixUpdateJob::dispatch($tx->id)->onQueue('webhooks');
            }

            Log::channel('webhooks')->info("✅ Webhook XFlow processado com sucesso", [
                'transaction_id' => $tx->id,
                'status'         => $newEnum->value,
                'paid_at'        => $paidAt,
            ]);

            return response()->json([
                'success' => true,
                'status'  => $newEnum->value,
                'e2e_id'  => $incomingE2E
            ]);

        } catch (\Throwable $e) {

            Log::channel('webhooks')->error("🚨 ERRO WEBHOOK XFlow", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'internal_server_error'
            ], 500);
        }
    }

    /**
     * 🔐 Gera E2E interno no padrão FEBRABAN
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
