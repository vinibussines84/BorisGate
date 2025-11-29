<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PluggouPayoutWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = $request->all();

        Log::info('[Pluggou Payout] Webhook recebido', ['payload' => $payload]);

        $eventType = $payload['event_type'] ?? null;
        $data      = $payload['data'] ?? null;

        if ($eventType !== 'withdrawal' || !is_array($data)) {
            return response()->json(['message' => 'ignored - invalid payload'], 422);
        }

        $providerId = $data['id'] ?? null;
        $status     = strtolower($data['status'] ?? '');
        $amount     = $data['amount'] ?? null;
        $liquid     = $data['liquid_amount'] ?? null;
        $paidAt     = $data['paid_at'] ?? null;

        if (!$providerId) {
            return response()->json(['message' => 'ignored - missing id'], 422);
        }

        $withdraw = Withdraw::where('provider_reference', $providerId)->first();

        if (!$withdraw) {
            Log::warning('[Pluggou Payout] Withdraw não encontrado', [
                'provider_reference' => $providerId,
                'status'             => $status,
            ]);
            return response()->json(['message' => 'ok - withdraw not found']);
        }

        // Evita sobrescrever registros finalizados
        if (in_array($withdraw->status, ['paid', 'rejected', 'failed'], true)) {
            Log::info('[Pluggou Payout] Ignorado, saque já finalizado', [
                'withdraw_id' => $withdraw->id,
                'status'      => $withdraw->status,
            ]);
            return response()->json([
                'message'  => 'ok - already finalized',
                'withdraw' => ['id' => $withdraw->id, 'status' => $withdraw->status],
            ]);
        }

        // 🔁 Mapeamento oficial de status Pluggou → internos
        $withdraw->status = match ($status) {
            'pending'  => 'pending',
            'approved' => 'processing',
            'paid'     => 'paid',
            'rejected' => 'failed',
            'failed'   => 'failed',
            default    => $withdraw->status,
        };

        // 🔢 Atualiza valores (centavos → reais)
        if (Schema::hasColumn('withdraws', 'amount') && $liquid !== null) {
            $withdraw->amount = $liquid / 100;
        }

        if (Schema::hasColumn('withdraws', 'gross_amount') && $amount !== null) {
            $withdraw->gross_amount = $amount / 100;
        }

        // ⏱️ Salva data de pagamento (paid_at)
        if (Schema::hasColumn('withdraws', 'completed_at') && $paidAt) {
            $withdraw->completed_at = $paidAt;
        }

        // 🧾 Armazena payload completo
        if (Schema::hasColumn('withdraws', 'meta')) {
            $meta = (array) ($withdraw->meta ?? []);
            $meta['pluggou_payout_webhook'] = $payload;
            $withdraw->meta = $meta;
        }

        $withdraw->save();

        Log::info('[Pluggou Payout] Withdraw atualizado com sucesso', [
            'withdraw_id'        => $withdraw->id,
            'provider_reference' => $providerId,
            'status'             => $withdraw->status,
        ]);

        return response()->json([
            'message'  => 'ok',
            'withdraw' => ['id' => $withdraw->id, 'status' => $withdraw->status],
        ]);
    }
}
