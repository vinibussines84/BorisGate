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
        | 1) Validar payload
        |--------------------------------------------------------------------------
        */
        $eventType = data_get($payload, 'event_type');
        $data      = data_get($payload, 'data');

        if ($eventType !== 'withdrawal' || !is_array($data)) {
            return response()->json(['message' => 'ignored - invalid payload'], 422);
        }

        $providerId = data_get($data, 'id');
        $status     = strtolower(data_get($data, 'status', 'pending'));
        $paidAt     = data_get($data, 'paid_at');

        if (!$providerId) {
            return response()->json(['message' => 'ignored - missing id'], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | 2) Buscar saque
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
        | 3) Idempotência
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
        | 4) Mapear statuses
        |--------------------------------------------------------------------------
        */
        $isPaid = in_array($status, ['paid', 'success', 'completed'], true);

        // sua regra: QUALQUER status ≠ PAID → failed
        $isFailed = !$isPaid;

        /*
        |--------------------------------------------------------------------------
        | 5) 🔥 FAILED (QUALQUER status diferente de PAID)
        |--------------------------------------------------------------------------
        */
        if ($isFailed) {

            Log::warning('[Pluggou Payout] Falhou — estornando BRUTO', [
                'withdraw_id' => $withdraw->id,
                'provider_id' => $providerId,
                'status'      => $status,
            ]);

            // 🚨 CHAMAR refundLocal (a versão correta agora)
            $withdrawService->refundLocal(
                $withdraw,
                "Falha no PIXOUT via Pluggou — status recebido: {$status}"
            );

            return response()->json([
                'success' => true,
                'status'  => 'failed',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 6) 💚 PAID → marcar como pago
        |--------------------------------------------------------------------------
        */
        if ($isPaid) {

            Log::info('[Pluggou Payout] Saque PAGO', [
                'withdraw_id' => $withdraw->id,
                'provider_id' => $providerId,
                'paid_at'     => $paidAt,
            ]);

            $withdrawService->markAsPaid($withdraw, $payload);

            return response()->json([
                'success' => true,
                'status'  => 'paid',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 7) Nunca chega aqui, mas deixamos por segurança
        |--------------------------------------------------------------------------
        */
        return response()->json(['message' => 'ok - processed']);
    }
}
