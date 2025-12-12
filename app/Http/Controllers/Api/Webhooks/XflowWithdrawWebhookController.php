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
        Log::info('[XFLOW][WITHDRAW][WEBHOOK] 📩 Recebido', $request->all());

        $data = $request->validate([
            'transaction_id' => ['required', 'string'],
            'status'         => ['required', 'string'],
            'amount'         => ['required', 'numeric'],
            'fee'            => ['nullable', 'numeric'],
            'ispb'           => ['nullable'],
            'nome_recebedor' => ['nullable', 'string'],
            'cpf_recebedor'  => ['nullable', 'string'],
        ]);

        $withdraw = Withdraw::where('provider_reference', $data['transaction_id'])->first();

        if (!$withdraw) {
            Log::warning('[XFLOW][WITHDRAW][WEBHOOK] ❌ Saque não encontrado', [
                'transaction_id' => $data['transaction_id'],
            ]);

            return response()->json(['ok' => true]);
        }

        if (in_array($withdraw->status, ['paid','failed','canceled'], true)) {
            return response()->json(['ok' => true]);
        }

        if (strtoupper($data['status']) === 'COMPLETED') {
            $this->withdrawService->markAsPaid(
                $withdraw,
                payload: $data,
                extra: [
                    'paid_at' => now(),
                    'provider_status' => 'completed',
                ]
            );

            return response()->json(['ok' => true]);
        }

        $this->withdrawService->refundLocal(
            $withdraw,
            'XFlow retornou status inválido ou erro'
        );

        return response()->json(['ok' => true]);
    }
}
