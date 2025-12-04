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
use Illuminate\Support\Str;
use Carbon\Carbon;

class WebhookPluggouController extends Controller
{
    public function __invoke(Request $request, WalletService $wallet)
    {
        try {
            $raw  = $request->json()->all() ?: json_decode($request->getContent(), true) ?: [];
            $data = data_get($raw, 'data', []);

            Log::info("📩 Webhook Pluggou recebido", ['payload' => $raw]);

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

            // ⚙️ Se já foi finalizada, ignora
            if (in_array($tx->status, ['PAID', 'FAILED'], true)) {
                return response()->json(['ignored' => true]);
            }

            // 🔄 Normaliza status
            $normalized = StatusMap::normalize(data_get($data, 'status'));
            $newEnum    = TransactionStatus::fromLoose($normalized);
            $oldEnum    = TransactionStatus::tryFrom($tx->status);

            // 💡 E2E nunca pode ser nulo — gera se não vier do Pluggou
            $incomingE2E = data_get($data, 'e2e_id');
            if (empty($incomingE2E)) {
                $incomingE2E = $this->generateFallbackE2E($tx);
                Log::warning('⚠️ Gerado E2E interno (faltante no webhook Pluggou)', [
                    'transaction_id' => $tx->id,
                    'generated_e2e'  => $incomingE2E,
                ]);
            }

            // ⚖️ Aplica alteração de saldo
            $wallet->applyStatusChange($tx, $oldEnum, $newEnum);

            // 💾 Atualiza transação local
            $tx->updateQuietly([
                'status'           => $newEnum->value,
                'provider_payload' => $data,
                'e2e_id'           => $incomingE2E,
                'paid_at'          => data_get($data, 'paid_at') ?: $tx->paid_at,
            ]);

            // 🔔 Dispara webhook do sistema do cliente
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
                'e2e_id'  => $tx->e2e_id,
            ]);

        } catch (\Throwable $e) {

            Log::error("🚨 ERRO WEBHOOK PLUGGOU", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'internal_server_error'], 500);
        }
    }

    /**
     * 🔐 Gera E2E interno quando o provedor não envia
     */
    private function generateFallbackE2E(Transaction $tx): string
    {
        $timestamp = Carbon::now('UTC')->format('YmdHis');
        $random    = strtoupper(Str::random(6));
        $userPart  = str_pad((string) ($tx->user_id ?? 0), 3, '0', STR_PAD_LEFT);
        $txPart    = str_pad((string) $tx->id, 4, '0', STR_PAD_LEFT);

        return "E2E{$timestamp}{$userPart}{$txPart}{$random}";
    }
}
