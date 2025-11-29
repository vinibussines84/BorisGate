<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;

class RapdynWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::info('[RapdynWebhook] Payload recebido', [
            'payload' => $payload,
        ]);

        // ----------------------------------------------------
        // VALIDAÇÃO BÁSICA
        // ----------------------------------------------------
        if (!isset($payload['id']) || !isset($payload['event'])) {
            Log::warning('[RapdynWebhook] Payload inválido', ['payload' => $payload]);
            return response()->json(['error' => 'Invalid webhook payload'], 400);
        }

        $providerId = $payload['id'];
        $event      = strtolower($payload['event']);

        // ----------------------------------------------------
        // LOCALIZA TRANSAÇÃO
        // ----------------------------------------------------
        $tx = Transaction::where('provider_transaction_id', $providerId)->first();

        if (!$tx) {
            Log::warning('[RapdynWebhook] Transação não encontrada', [
                'provider_transaction_id' => $providerId
            ]);
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        // ----------------------------------------------------
        // DUPLICIDADE — já processada anteriormente
        // ----------------------------------------------------
        if (in_array($tx->status, [
            TransactionStatus::PAGA->value,
            TransactionStatus::UNDER_REVIEW->value,
        ], true)) {
            Log::info('[RapdynWebhook] Webhook duplicado ignorado', [
                'tx_id' => $tx->id,
                'status_atual' => $tx->status,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Transação já processada anteriormente.'
            ]);
        }

        // ----------------------------------------------------
        // EVENTO: "PAID"
        // ----------------------------------------------------
        if ($event === 'paid') {

            $valorCentavos = (int) ($payload['total'] ?? 0);
            $valorReais = $valorCentavos / 100;

            $novoStatus = $valorReais > 160
                ? TransactionStatus::UNDER_REVIEW->value
                : TransactionStatus::PAGA->value;

            $tx->update([
                'status'                  => $novoStatus,
                'paid_at'                 => now(),
                'e2e_id'                  => data_get($payload, 'pix.end2EndId'),
                'provider_total'          => $valorCentavos,
                'provider_platform_tax'   => data_get($payload, 'platform_tax'),
                'provider_transaction_tax'=> data_get($payload, 'transaction_tax'),
                'provider_comission'      => data_get($payload, 'comission'),
                'provider_payload'        => array_merge($tx->provider_payload ?? [], [
                    'webhook_paid' => $payload,
                ]),
            ]);

            Log::info('[RapdynWebhook] Transação atualizada para PAGA', [
                'tx_id' => $tx->id,
                'status' => $novoStatus,
                'e2e_id' => data_get($payload, 'pix.end2EndId'),
                'valor' => $valorReais,
            ]);

            // Credita saldo se não estiver em revisão
            if ($novoStatus !== TransactionStatus::UNDER_REVIEW->value) {
                if (method_exists($tx, 'creditUserBalance')) {
                    try {
                        $tx->creditUserBalance();
                        Log::info('[RapdynWebhook] Saldo creditado com sucesso', [
                            'tx_id' => $tx->id,
                            'valor' => $valorReais,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('[RapdynWebhook] Erro ao creditar saldo', [
                            'tx_id' => $tx->id,
                            'erro' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json(['success' => true]);
        }

        // ----------------------------------------------------
        // EVENTO: CANCELADO / EXPIRADO
        // ----------------------------------------------------
        if (in_array($event, ['canceled', 'cancel', 'expired'], true)) {

            $tx->update([
                'status' => TransactionStatus::ERRO->value,
                'provider_payload' => array_merge($tx->provider_payload ?? [], [
                    'webhook_cancelled' => $payload,
                ]),
            ]);

            Log::info('[RapdynWebhook] Transação cancelada/expirada', [
                'tx_id' => $tx->id,
                'provider_transaction_id' => $providerId,
            ]);

            return response()->json(['success' => true]);
        }

        // ----------------------------------------------------
        // EVENTO DESCONHECIDO
        // ----------------------------------------------------
        $tx->update([
            'provider_payload' => array_merge($tx->provider_payload ?? [], [
                'webhook_unknown' => $payload,
            ]),
        ]);

        Log::warning('[RapdynWebhook] Evento desconhecido recebido', [
            'tx_id' => $tx->id,
            'event' => $event,
        ]);

        return response()->json(['success' => true]);
    }
}
