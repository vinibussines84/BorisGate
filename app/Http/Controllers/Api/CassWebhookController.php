<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Enums\TransactionStatus;

class CassWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // -------------------------------------------------
        // LOGA TODO O PAYLOAD PARA AUDITORIA
        // -------------------------------------------------
        Log::info('Cass Pagamentos Webhook recebido', [
            'payload' => $request->all(),
        ]);

        // -------------------------------------------------
        // VALIDAÇÃO DO FORMATO BÁSICO
        // -------------------------------------------------
        if (! $request->has('data.id')) {
            return response()->json(['error' => 'Payload inválido'], 400);
        }

        $data = $request->input('data');
        $idCass      = $data['id'] ?? null;
        $externalRef = $data['externalRef'] ?? null;
        $statusCass  = strtolower($data['status'] ?? '');
        $method      = strtolower($data['paymentMethod'] ?? '');
        $amount      = $data['amount'] ?? null;
        $paidAmount  = $data['paidAmount'] ?? null;

        // -------------------------------------------------
        // LOCALIZA TRANSAÇÃO (POR externalRef OU provider_transaction_id)
        // -------------------------------------------------
        $transaction = Transaction::query()
            ->where('external_reference', $externalRef)
            ->orWhere('provider_transaction_id', $idCass)
            ->first();

        if (! $transaction) {
            Log::warning('Cass Webhook: transação não encontrada', [
                'idCass' => $idCass,
                'externalRef' => $externalRef,
            ]);
            return response()->json(['error' => 'Transação não encontrada'], 404);
        }

        // -------------------------------------------------
        // CONDIÇÃO: SÓ MUDA PARA "PAGA" QUANDO FOR PIX + PAID
        // -------------------------------------------------
        $novoStatus = $transaction->status;

        if ($method === 'pix' && $statusCass === 'paid') {
            $novoStatus = TransactionStatus::PAGA;
        }

        // -------------------------------------------------
        // ATUALIZA TRANSAÇÃO (mantém demais dados do webhook)
        // -------------------------------------------------
        $transaction->update([
            'status' => $novoStatus,
            'amount' => $amount ? ($amount / 100) : $transaction->amount,
            'provider_payload' => array_merge($transaction->provider_payload ?? [], [
                'cass_webhook' => $data,
            ]),
        ]);

        Log::info('Cass Webhook: transação atualizada', [
            'transaction_id' => $transaction->id,
            'novo_status'    => $novoStatus->value,
            'metodo'         => $method,
            'statusCass'     => $statusCass,
        ]);

        return response()->json(['success' => true]);
    }
}
