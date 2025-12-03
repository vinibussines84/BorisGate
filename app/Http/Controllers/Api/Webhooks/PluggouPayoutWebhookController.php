<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Withdraw;
use App\Enums\WithdrawStatus;
use App\Services\WalletService;
use App\Jobs\SendWebhookWithdrawUpdateJob;

class PluggouPayoutWebhookController extends Controller
{
    public function __invoke(Request $request, WalletService $wallet)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | 1) Normalização
            |--------------------------------------------------------------------------
            */
            $raw = $request->json()->all()
                ?: json_decode($request->getContent(), true)
                ?: [];

            Log::info("📩 Webhook Pluggou Withdrawal recebido", ['payload' => $raw]);

            $eventType = data_get($raw, 'event_type');
            $data      = data_get($raw, 'data', []);

            if ($eventType !== 'withdrawal') {
                return response()->json(['ignored' => true]);
            }

            $providerId  = data_get($data, 'id');
            $status      = strtolower(data_get($data, 'status', 'unknown'));
            $e2e         = data_get($data, 'e2e_id');
            $paidAt      = data_get($data, 'paid_at');
            $amount      = data_get($data, 'amount');
            $liquid      = data_get($data, 'liquid_amount');

            if (!$providerId) {
                return response()->json(['error' => 'missing_reference'], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 2) Buscar saque com LOCK
            |--------------------------------------------------------------------------
            */
            $withdraw = Withdraw::where('provider_reference', $providerId)
                ->lockForUpdate()
                ->first();

            if (!$withdraw) {
                Log::warning("⚠️ Withdraw não encontrado no webhook Pluggou", [
                    'provider_reference' => $providerId
                ]);
                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Idempotência
            |--------------------------------------------------------------------------
            */
            if (in_array($withdraw->status, ['paid', 'failed', 'rejected'])) {
                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | 4) Mapa de status Pluggou → Interno
            |--------------------------------------------------------------------------
            */
            $map = [
                'pending'    => WithdrawStatus::PROCESSING,
                'approved'   => WithdrawStatus::PROCESSING,
                'processing' => WithdrawStatus::PROCESSING,
                'sent'       => WithdrawStatus::PROCESSING,
                'paid'       => WithdrawStatus::PAID,
                'success'    => WithdrawStatus::PAID,
                'completed'  => WithdrawStatus::PAID,
                'failed'     => WithdrawStatus::FAILED,
                'error'      => WithdrawStatus::FAILED,
                'rejected'   => WithdrawStatus::FAILED,
                'canceled'   => WithdrawStatus::FAILED,
                'cancelled'  => WithdrawStatus::FAILED,
            ];

            $newStatus = $map[$status] ?? null;

            if (!$newStatus) {
                Log::info("ℹ️ Webhook ignorado (status não mapeado)", [
                    'status' => $status,
                ]);
                return response()->json(['ignored' => true]);
            }

            $oldStatus = WithdrawStatus::tryFrom($withdraw->status);

            /*
            |--------------------------------------------------------------------------
            | 5) Aplicar mudança financeira
            |--------------------------------------------------------------------------
            */
            $wallet->applyWithdrawStatusChange($withdraw, $oldStatus, $newStatus);

            /*
            |--------------------------------------------------------------------------
            | 6) Atualizar withdraw
            |--------------------------------------------------------------------------
            */
            $withdraw->updateQuietly([
                'status'                  => $newStatus->value,
                'provider_payload'        => $raw,
                'e2e_id'                  => $e2e ?? $withdraw->e2e_id,
                'paid_at'                 => $paidAt ?? $withdraw->paid_at,
                'amount'                  => $liquid ? ($liquid / 100) : $withdraw->amount,
                'gross_amount'            => $amount ? ($amount / 100) : $withdraw->gross_amount,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 7) Enviar webhook OUT de atualização
            |--------------------------------------------------------------------------
            */
            if ($newStatus === WithdrawStatus::PAID &&
                $withdraw->user?->webhook_enabled &&
                $withdraw->user?->webhook_out_url
            ) {
                SendWebhookWithdrawUpdateJob::dispatch($withdraw->id);
            }

            return response()->json([
                'success' => true,
                'status'  => $newStatus->value,
            ]);

        } catch (\Throwable $e) {

            Log::error("🚨 ERRO NO WEBHOOK PLUGGOU CASHOUT", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
