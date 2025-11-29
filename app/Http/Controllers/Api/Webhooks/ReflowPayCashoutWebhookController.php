<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReflowPayCashoutWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = $request->all();

        Log::info('[ReflowPay Cashout Webhook] Recebido', $payload);

        try {
            if (empty($payload['orderId']) || empty($payload['status'])) {
                Log::warning('[ReflowPay Cashout Webhook] Payload inválido', $payload);
                return response()->json(['ignored' => true], 422);
            }

            if ($payload['status'] === 'created') {
                Log::info('[ReflowPay Cashout Webhook] Ignorado (status created)', [
                    'orderId' => $payload['orderId'],
                ]);
                return response()->json(['ignored' => true]);
            }

            $withdraw = Withdraw::where('idempotency_key', $payload['orderId'])->first();

            if (!$withdraw) {
                Log::warning('[ReflowPay Cashout Webhook] Saque não encontrado', [
                    'orderId' => $payload['orderId'],
                ]);
                return response()->json(['not_found' => true], 404);
            }

            if ($withdraw->status === 'paid') {
                Log::info('[ReflowPay Cashout Webhook] Já pago, ignorado', [
                    'orderId' => $payload['orderId'],
                ]);
                return response()->json(['already_paid' => true]);
            }

            if ($payload['status'] === 'paid') {
                $meta = $withdraw->meta ?? [];
                $meta['reflow_webhook'] = $payload;

                $withdraw->update([
                    'status'             => 'paid',
                    'provider_reference' => $payload['transactionId'] ?? $withdraw->provider_reference,
                    'meta'               => $meta,
                ]);

                Log::info('[ReflowPay Cashout Webhook] Saque confirmado como pago', [
                    'withdraw_id' => $withdraw->id,
                    'orderId'     => $payload['orderId'],
                    'transaction' => $payload['transactionId'] ?? null,
                    'e2e'         => $payload['endToEndId'] ?? null,
                ]);

                return response()->json(['success' => true]);
            }

            if (in_array($payload['status'], ['failed', 'canceled', 'rejected'])) {
                $meta = $withdraw->meta ?? [];
                $meta['reflow_webhook'] = $payload;

                $withdraw->update([
                    'status' => 'failed',
                    'meta'   => $meta,
                ]);

                Log::info('[ReflowPay Cashout Webhook] Saque marcado como falhado', [
                    'withdraw_id' => $withdraw->id,
                    'orderId'     => $payload['orderId'],
                    'status'      => $payload['status'],
                ]);

                return response()->json(['failed' => true]);
            }

            Log::info('[ReflowPay Cashout Webhook] Status não tratado', [
                'status'  => $payload['status'],
                'orderId' => $payload['orderId'],
            ]);

            return response()->json(['unhandled_status' => $payload['status']]);
        } catch (\Throwable $e) {
            Log::error('[ReflowPay Cashout Webhook] Erro ao processar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }
}
