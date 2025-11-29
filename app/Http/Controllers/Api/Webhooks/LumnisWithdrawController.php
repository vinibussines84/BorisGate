<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LumnisWithdrawController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $data = $request->json()->all();

            $status     = strtoupper(data_get($data, 'status', ''));
            $reference  = data_get($data, 'id');
            $receipt    = data_get($data, 'receipt.0', []);

            // ✅ Apenas processa se veio status APPROVED
            if ($status !== 'APPROVED' || !$reference) {
                return response()->json(['ignored' => true, 'reason' => 'status_not_approved_or_missing_reference']);
            }

            /** @var Withdraw|null $withdraw */
            $withdraw = Withdraw::query()
                ->where('provider', 'lumnis')
                ->where('provider_reference', $reference)
                ->first();

            if (!$withdraw) {
                Log::warning('⚠️ Lumnis webhook: saque não encontrado', ['reference' => $reference]);
                return response()->json(['error' => 'withdraw_not_found'], 404);
            }

            // ⚙️ Atualiza o saque como pago
            $withdraw->update([
                'status' => 'paid',
                'meta' => array_merge((array) $withdraw->meta, [
                    'endtoend'        => $receipt['endtoend'] ?? null,
                    'identifier'      => $receipt['identifier'] ?? null,
                    'receiver_name'   => $receipt['receiver_name'] ?? null,
                    'receiver_bank'   => $receipt['receiver_bank'] ?? null,
                    'receiver_bank_ispb' => $receipt['receiver_bank_ispb'] ?? null,
                    'refused_reason'  => $receipt['refused_reason'] ?? null,
                    'webhook_payload' => $data,
                    'paid_at'         => now()->toDateTimeString(),
                ]),
            ]);

            return response()->json([
                'received' => true,
                'status'   => 'paid',
                'reference' => $reference,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ Erro no webhook Lumnis Withdraw', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
