<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use Illuminate\Support\Facades\DB;

class PluggouWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        Log::info('🔔 PLUGGOU WEBHOOK RECEBIDO', [
            'payload' => $request->all(),
        ]);

        $event      = $request->input('event_type');
        $providerId = $request->input('data.id');
        $status     = $request->input('data.status');
        $e2e        = $request->input('data.e2e_id');

        if (!$providerId) {
            Log::warning('Pluggou Webhook sem provider_transaction_id');
            return response()->json(['ok' => true]);
        }

        // 🔍 Buscar transação
        $tx = Transaction::where('provider', 'Interna')
            ->where('provider_transaction_id', $providerId)
            ->first();

        if (!$tx) {
            Log::warning('❗ Transação Pluggou não encontrada', [
                'provider_transaction_id' => $providerId,
            ]);
            return response()->json(['ok' => true]);
        }

        // 🚫 Ignorar se já paga
        if ($tx->isPaga()) {
            Log::info('⏳ Webhook ignorado — transação já paga', [
                'id' => $tx->id,
            ]);
            return response()->json(['ok' => true]);
        }

        /**
         * ================================================================
         * 🔥 NOVA REGRA:
         * - Até R$ 300  → processa normal (paga automaticamente)
         * - Acima 300   → vai para ANÁLISE MANUAL
         * ================================================================
         */
        if ($status === 'paid') {

            // -------------------------------
            // 🔶 Acima de 300 → análise
            // -------------------------------
            if ($tx->amount > 300) {

                $tx->status = 'under_review';
                $tx->provider_payload = $request->all();
                $tx->save();

                Log::warning('⚠️ Transação acima de R$300 — ANÁLISE MANUAL', [
                    'id'     => $tx->id,
                    'amount' => $tx->amount,
                ]);

                return response()->json(['ok' => true]);
            }

            // ---------------------------------
            // 🔵 Até 300 → paga automaticamente
            // ---------------------------------
            DB::transaction(function () use ($tx, $e2e, $request) {
                $tx->status  = TransactionStatus::PAGA;
                $tx->e2e_id  = $e2e;
                $tx->paid_at = now();
                $tx->provider_payload = $request->all();
                $tx->save();
            });

            Log::info('✅ Transação marcada como PAGA (Pluggou)', [
                'id'  => $tx->id,
                'e2e' => $e2e,
            ]);

        } else {

            // -------------------------------------------
            // 🔹 Não é paid → ignorar
            // -------------------------------------------
            Log::info('ℹ️ Webhook Pluggou ignorado (status != paid)', [
                'status' => $status,
                'id'     => $tx->id,
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
