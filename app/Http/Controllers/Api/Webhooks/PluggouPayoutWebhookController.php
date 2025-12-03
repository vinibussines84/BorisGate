<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Withdraw\WithdrawService;

class PluggouPayoutWebhookController extends Controller
{
    public function __invoke(Request $request, WithdrawService $withdrawService)
    {
        $payload = $request->all();

        Log::info('[Pluggou Payout] Webhook recebido', ['payload' => $payload]);

        /*
        |--------------------------------------------------------------------------
        | 1) Validar payload base
        |--------------------------------------------------------------------------
        */
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

        /*
        |--------------------------------------------------------------------------
        | 2) Buscar saque no sistema
        |--------------------------------------------------------------------------
        */
        $withdraw = Withdraw::where('provider_reference', $providerId)->first();

        if (!$withdraw) {
            Log::warning('[Pluggou Payout] Withdraw não encontrado', [
                'provider_reference' => $providerId,
                'status'             => $status,
            ]);
            return response()->json(['message' => 'ok - withdraw not found']);
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Idempotência — já finalizado
        |--------------------------------------------------------------------------
        */
        if (in_array($withdraw->status, ['paid', 'failed'], true)) {
            Log::info('[Pluggou Payout] Ignorado — saque já finalizado', [
                'withdraw_id' => $withdraw->id,
                'status'      => $withdraw->status,
            ]);
            return response()->json(['message' => 'ok - already finalized']);
        }

        /*
        |--------------------------------------------------------------------------
        | 4) Mapear status Pluggou
        |--------------------------------------------------------------------------
        */
        $isPaid = in_array($status, ['paid', 'success', 'completed'], true);

        $isFailed = in_array($status, [
            'failed', 'error', 'rejected', 'canceled', 'cancelled', 'expired'
        ], true);

        /*
        |--------------------------------------------------------------------------
        | 5) 💥 FALHOU → estorna imediatamente
        |--------------------------------------------------------------------------
        */
        if ($isFailed) {

            Log::warning('[Pluggou Payout] Saque FALHOU — estornando BRUTO', [
                'withdraw_id' => $withdraw->id,
                'provider_id' => $providerId,
                'status'      => $status,
            ]);

            // chama método público do WithdrawService
            $withdrawService->refundWebhookFailed($withdraw, $payload);

            return response()->json([
                'success' => true,
                'status'  => 'failed'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 6) 💚 PAGO → confirmar
        |--------------------------------------------------------------------------
        */
        if ($isPaid) {

            Log::info('[Pluggou Payout] Saque PAGO', [
                'withdraw_id' => $withdraw->id,
                'provider_id' => $providerId,
                'paid_at'     => $paidAt,
            ]);

            // chama método público do service
            $withdrawService->markAsPaid($withdraw, $payload);

            return response()->json([
                'success' => true,
                'status'  => 'paid'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 7) Status intermediário
        |--------------------------------------------------------------------------
        */
        Log::info('[Pluggou Payout] Status intermediário ignorado', [
            'withdraw_id' => $withdraw->id,
            'provider_id' => $providerId,
            'status'      => $status,
        ]);

        return response()->json(['message' => 'ok - intermediate']);
    }
}
