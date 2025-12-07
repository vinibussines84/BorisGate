<?php

namespace App\Http\Controllers\Api\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use App\Support\StatusMap;
use App\Services\WalletService;
use App\Jobs\SendWebhookPixUpdateJob;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WebhookCnInController extends Controller
{
    public function handle(Request $request, WalletService $wallet)
    {
        // ✔ Garantir que o conteúdo seja JSON
        if (!$request->isJson()) {
            return response()->json([
                'error' => 'Invalid content type. JSON expected.'
            ], 415);
        }

        $payload = $request->json()->all();

        Log::channel('webhooks')->info('📥 Webhook GETPAY recebido (CN IN)', [
            'payload' => $payload
        ]);

        try {
            /*
            |--------------------------------------------------------------------------
            | CAMPOS ESPERADOS DA GETPAY
            | type -> PAYIN_CONFIRMED
            | externalId -> identificador local
            | uuid -> identificador do provedor
            | amount -> valor
            | status -> "paid"
            | endToEndId -> E2E real do BACEN
            |--------------------------------------------------------------------------
            */

            $externalId = data_get($payload, 'externalId');
            $uuid       = data_get($payload, 'uuid');

            if (!$externalId && !$uuid) {
                return response()->json(['error' => 'missing_reference'], 422);
            }

            // Primeira tentativa → localizar transação pelo external_reference
            $tx = Transaction::where('external_reference', $externalId)
                ->lockForUpdate()
                ->first();

            // Segunda tentativa → localizar pelo provider_transaction_id (uuid)
            if (!$tx && $uuid) {
                $tx = Transaction::where('provider_transaction_id', $uuid)
                    ->lockForUpdate()
                    ->first();
            }

            if (!$tx) {
                return response()->json(['error' => 'transaction_not_found'], 404);
            }

            // ✔ Se já está PAID ou FAILED → ignorar
            if (in_array($tx->status, ['PAID', 'FAILED'], true)) {
                Log::channel('webhooks')->info("⚠ GETPAY webhook ignorado — transação já finalizada", [
                    'transaction_id' => $tx->id,
                    'status'         => $tx->status
                ]);

                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | NORMALIZAR STATUS (status = "paid")
            |--------------------------------------------------------------------------
            */
            $normalized = StatusMap::normalize(data_get($payload, 'status'));
            $newEnum    = TransactionStatus::fromLoose($normalized);
            $oldEnum    = TransactionStatus::tryFrom($tx->status);

            // ✔ Aplicar efeito de saldo APENAS se o status realmente mudou
            $wallet->applyStatusChange($tx, $oldEnum, $newEnum);

            /*
            |--------------------------------------------------------------------------
            | E2E real ou gerado se ausente
            |--------------------------------------------------------------------------
            */
            $incomingE2E = data_get($payload, 'endToEndId');

            if ($newEnum === TransactionStatus::PAID && empty($incomingE2E)) {
                $incomingE2E = $this->generateFallbackE2E($tx);

                Log::channel('webhooks')->warning('⚠ Gerado E2E interno (faltante no webhook GETPAY)', [
                    'transaction_id' => $tx->id,
                    'generated_e2e'  => $incomingE2E,
                ]);
            } else {
                // se não veio, mantém o anterior
                $incomingE2E = $incomingE2E ?: $tx->e2e_id;
            }

            $paidAt = data_get($payload, 'processed_at') ?: $tx->paid_at;

            /*
            |--------------------------------------------------------------------------
            | ATUALIZAR TRANSACAO LOCAL
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
            | WEBHOOK PARA O CLIENTE (SE HABILITADO)
            |--------------------------------------------------------------------------
            */
            if (
                $newEnum === TransactionStatus::PAID &&
                $tx->user?->webhook_enabled &&
                $tx->user?->webhook_in_url
            ) {
                SendWebhookPixUpdateJob::dispatch($tx->id)->onQueue('webhooks');
            }

            Log::channel('webhooks')->info("✅ GETPAY webhook processado com sucesso", [
                'transaction_id' => $tx->id,
                'status'         => $newEnum->value,
            ]);

            return response()->json([
                'success' => true,
                'status'  => $newEnum->value,
                'e2e_id'  => $incomingE2E,
            ]);
        } catch (\Throwable $e) {

            Log::channel('webhooks')->error("🚨 ERRO WEBHOOK GETPAY (CN IN)", [
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
        $timestamp = Carbon::now('UTC')->format('YmdHis');
        $random    = strtoupper(Str::random(6));
        $userPart  = str_pad((string) ($tx->user_id ?? 0), 3, '0', STR_PAD_LEFT);
        $txPart    = str_pad((string) $tx->id, 4, '0', STR_PAD_LEFT);

        return "E2E{$timestamp}{$userPart}{$txPart}{$random}";
    }
}
