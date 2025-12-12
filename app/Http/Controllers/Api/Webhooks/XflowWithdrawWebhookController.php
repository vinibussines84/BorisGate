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
        Log::info('[XFLOW][WITHDRAW][WEBHOOK] 📩 Recebido', [
            'payload' => $request->all(),
        ]);

        $data = $request->validate([
            'transaction_id' => ['nullable', 'string'],
            'external_id'    => ['nullable', 'string'],
            'status'         => ['required', 'string'],
            'amount'         => ['required', 'numeric'],
            'fee'            => ['nullable', 'numeric'],
            'ispb'           => ['nullable', 'string'],
            'nome_recebedor' => ['nullable', 'string'],
            'cpf_recebedor'  => ['nullable', 'string'],
        ]);

        /**
         * 🔎 1) Busca pelo transaction_id (provider)
         */
        $withdraw = null;

        if (!empty($data['transaction_id'])) {
            $withdraw = Withdraw::where(
                'provider_reference',
                $data['transaction_id']
            )->first();
        }

        /**
         * 🔎 2) Fallback: busca pelo external_id (SEU)
         */
        if (!$withdraw && !empty($data['external_id'])) {
            $withdraw = Withdraw::where(
                'external_id',
                $data['external_id']
            )->first();
        }

        if (!$withdraw) {
            Log::warning('[XFLOW][WITHDRAW][WEBHOOK] ❌ Saque não encontrado', [
                'transaction_id' => $data['transaction_id'] ?? null,
                'external_id'    => $data['external_id'] ?? null,
            ]);

            return response()->json(['ok' => true]);
        }

        /**
         * 🔒 Idempotência
         */
        if (in_array($withdraw->status, [
            Withdraw::STATUS_PAID,
            Withdraw::STATUS_FAILED,
            Withdraw::STATUS_CANCELED,
        ], true)) {
            return response()->json(['ok' => true]);
        }

        $providerStatus = strtoupper($data['status']);

        /**
         * ✅ COMPLETED
         */
        if ($providerStatus === 'COMPLETED') {

            // 🔥 garante que o provider_reference fique salvo
            if (empty($withdraw->provider_reference) && !empty($data['transaction_id'])) {
                $this->withdrawService->updateProviderReference(
                    $withdraw,
                    $data['transaction_id'],
                    strtolower($providerStatus),
                    $data
                );
            }

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
         * ❌ FAILED / CANCELED
         */
        if (in_array($providerStatus, ['FAILED', 'CANCELED'], true)) {

            $this->withdrawService->refundLocal(
                $withdraw,
                "XFlow retornou status {$providerStatus}"
            );
        }

        return response()->json(['ok' => true]);
    }
}
