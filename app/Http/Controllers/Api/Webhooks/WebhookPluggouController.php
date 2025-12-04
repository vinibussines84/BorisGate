<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Support\StatusMap;
use App\Enums\TransactionStatus;
use App\Jobs\SendWebhookPixUpdateJob;
use App\Services\WalletService;

class WebhookPluggouController extends Controller
{
    public function __invoke(Request $request, WalletService $wallet)
    {
        try {
            $raw  = $request->json()->all() ?: json_decode($request->getContent(), true) ?: [];
            $data = data_get($raw, 'data', []);

            Log::info("📩 Webhook Pluggou", ['payload' => $raw]);

            $providerId = data_get($data, 'id');
            if (!$providerId) {
                return response()->json(['error' => 'missing_provider_transaction_id'], 422);
            }

            $tx = Transaction::where('provider_transaction_id', $providerId)
                ->lockForUpdate()
                ->first();

            if (!$tx) {
                return response()->json(['error' => 'transaction_not_found'], 404);
            }

            if (in_array($tx->status, ['PAID', 'FAILED'], true)) {
                return response()->json(['ignored' => true]);
            }

            $normalized = StatusMap::normalize(data_get($data, 'status'));
            $newEnum    = TransactionStatus::fromLoose($normalized);
            $oldEnum    = TransactionStatus::tryFrom($tx->status);

            /** aplica saldo */
            $wallet->applyStatusChange($tx, $oldEnum, $newEnum);

            /** atualiza TX */
            $tx->updateQuietly([
                'status'           => $newEnum->value,
                'provider_payload' => $data,
                'e2e_id'           => data_get($data, 'e2e_id') ?: $tx->e2e_id,
                'paid_at'          => data_get($data, 'paid_at') ?: $tx->paid_at,
            ]);

            if (
                $newEnum === TransactionStatus::PAID &&
                $tx->user?->webhook_enabled &&
                $tx->user?->webhook_in_url
            ) {
                SendWebhookPixUpdateJob::dispatch($tx->id);
            }

            return response()->json([
                'success' => true,
                'status'  => $newEnum->value,
            ]);

        } catch (\Throwable $e) {

            Log::error("🚨 ERRO WEBHOOK PLUGGOU", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'internal_server_error'], 500);
        }
    }
}
