<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use App\Services\Withdraw\WithdrawService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XflowWithdrawWebhookController extends Controller
{
    public function __construct(
        private readonly WithdrawService $withdrawService
    ) {}

    public function __invoke(Request $request)
    {
        /**
         * 📩 LOG DE ENTRADA
         */
        Log::info('[XFLOW][WITHDRAW][WEBHOOK] 📩 Recebido', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        /**
         * ✅ VALIDAÇÃO DO PAYLOAD
         */
        $data = $request->validate([
            'transaction_id' => ['required', 'string'],
            'status'         => ['required', 'string'],
            'amount'         => ['required', 'numeric'],
            'fee'            => ['nullable', 'numeric'],
            'ispb'           => ['nullable', 'string'],
            'nome_recebedor' => ['nullable', 'string'],
            'cpf_recebedor'  => ['nullable', 'string'],
        ]);

        /**
         * 🔎 LOCALIZA O SAQUE PELO transaction_id DA XFLOW
         */
        $withdraw = Withdraw::where(
            'provider_reference',
            $data['transaction_id']
        )->first();

        if (! $withdraw) {
            Log::warning('[XFLOW][WITHDRAW][WEBHOOK] ❌ Saque não encontrado', [
                'transaction_id' => $data['transaction_id'],
            ]);

            // ⚠️ Sempre responder 200 para não gerar retry infinito
            return response()->json(['ok' => true]);
        }

        /**
         * 🔒 IDEMPOTÊNCIA — JÁ FINALIZADO
         */
        if (in_array($withdraw->status, [
            Withdraw::STATUS_PAID,
            Withdraw::STATUS_FAILED,
            Withdraw::STATUS_CANCELED,
        ], true)) {
            return response()->json(['ok' => true]);
        }

        /**
         * 🔁 NORMALIZA STATUS DO PROVIDER
         */
        $providerStatus = strtoupper($data['status']);

        /**
         * ✅ SAQUE CONCLUÍDO
         */
        if ($providerStatus === 'COMPLETED') {

            $this->withdrawService->markAsPaid(
                $withdraw,
                payload: $data,
                extra: [
                    'paid_at'         => now(),
                    'provider_status' => $providerStatus,
                ]
            );

            return response()->json(['ok' => true]);
        }

        /**
         * ❌ SAQUE FALHOU / CANCELADO
         */
        if (in_array($providerStatus, ['FAILED', 'CANCELED'], true)) {

            $this->withdrawService->refundLocal(
                $withdraw,
                "XFlow retornou status {$providerStatus}"
            );

            return response()->json(['ok' => true]);
        }

        /**
         * ⏳ STATUS INTERMEDIÁRIO (PENDING / PROCESSING / ETC)
         * → Apenas ignora e aguarda próximo webhook
         */
        Log::info('[XFLOW][WITHDRAW][WEBHOOK] ⏳ Status intermediário ignorado', [
            'withdraw_id'     => $withdraw->id,
            'provider_status' => $providerStatus,
        ]);

        return response()->json(['ok' => true]);
    }
}
