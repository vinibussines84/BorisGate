<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Withdraw\WithdrawService;

class PluggouPayoutWebhookController extends Controller
{
    public function __invoke(Request $request, WithdrawService $withdrawService)
    {
        $payload = $request->all();

        Log::info('[Pluggou Payout] Webhook recebido', ['payload' => $payload]);

        $eventType = data_get($payload, 'event_type');
        $data      = data_get($payload, 'data');

        if ($eventType !== 'withdrawal' || !is_array($data)) {
            return response()->json(['message' => 'ignored - invalid payload'], 422);
        }

        $providerId = data_get($data, 'id');
        $status     = strtolower(data_get($data, 'status', ''));
        $paidAt     = data_get($data, 'paid_at');

        if (!$providerId) {
            return response()->json(['message' => 'ignored - missing id'], 422);
        }

        // Buscar withdraw
        $withdraw = Withdraw::where('provider_reference', $providerId)->first();

        if (!$withdraw) {
            Log::warning('[Pluggou Payout] Withdraw não encontrado', [
                'provider_reference' => $providerId,
                'status' => $status,
            ]);
            return response()->json(['message' => 'ok - withdraw not found']);
        }

        // Idempotência (já pago ou já falhado)
        if (in_array($withdraw->status, ['paid', 'failed'], true)) {
            Log::info('[Pluggou Payout] Ignorado — saque já finalizado', [
                'withdraw_id' => $withdraw->id,
                'status'      => $withdraw->status,
            ]);
            return response()->json(['message' => 'ok - already finalized']);
        }

        /*
        |--------------------------------------------------------------------------
        | Mapa oficial de status Pluggou → interno
        |--------------------------------------------------------------------------
        */
        $isPaid = in_array($status, ['paid', 'success', 'completed'], true);

        $isFailed = in_array($status, [
            'failed', 'error', 'rejected', 'canceled', 'cancelled', 'expired'
        ], true);

        /*
        |--------------------------------------------------------------------------
        | FALHA → Estornar imediatamente
        |--------------------------------------------------------------------------
        */
        if ($isFailed) {

            Log::warning('[Pluggou Payout] Saque falhou — estornando...', [
                'withdraw_id' => $withdraw->id,
                'status' => $status,
            ]);

            // 🔥 estorna + marca como failed + salva payload + dispara webhook
            $withdrawService->refundWebhookFailed($withdraw, $payload);

            return response()->json(['success' => true, 'status' => 'failed']);
        }

        /*
        |--------------------------------------------------------------------------
        | PAGO → Confirmar saque
        |--------------------------------------------------------------------------
        */
        if ($isPaid) {

            Log::info('[Pluggou Payout] Saque confirmado (PAID)', [
                'withdraw_id' => $withdraw->id,
            ]);

            $withdrawService->markAsPaid($withdraw, $payload);

            return response()->json([
                'success' => true,
                'status'  => 'paid'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Status intermediários (processing/pending)
        |--------------------------------------------------------------------------
        */
        Log::info('[Pluggou Payout] Status intermediário ignorado', [
            'status' => $status,
            'withdraw_id' => $withdraw->id,
        ]);

        return response()->json(['message' => 'ok - intermediate']);
    }
}
