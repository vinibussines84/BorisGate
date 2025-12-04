<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookWithdrawUpdatedJob;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WebhookPluggouPixOutController extends Controller
{
    /**
     * Webhook Pluggou PIXOUT — Recebe notificações de saque.
     *
     * Exemplo de payload:
     * {
     *   "id": "f47b180c...",
     *   "event_type": "withdrawal",
     *   "data": {
     *      "id": "f47b180c...",
     *      "status": "paid",
     *      "e2e_id": "E2E...",
     *      "paid_at": "2025-12-04 22:25:51",
     *      "amount": 1300,
     *      "liquid_amount": 1280
     *   }
     * }
     */
    public function __invoke(Request $request)
    {
        try {
            $raw = $request->json()->all();
            Log::info('📩 Webhook Pluggou PIXOUT recebido', ['payload' => $raw]);

            $data = data_get($raw, 'data', []);
            $providerId = data_get($data, 'id');

            if (!$providerId) {
                return response()->json(['error' => 'missing_provider_id'], 422);
            }

            $withdraw = Withdraw::where('provider_reference', $providerId)->first();

            if (!$withdraw) {
                Log::warning('⚠️ Saque não encontrado para provider_reference', ['id' => $providerId]);
                return response()->json(['error' => 'withdraw_not_found'], 404);
            }

            // Evita reprocessar estados finais
            if (in_array(strtolower($withdraw->status), ['paid', 'failed'], true)) {
                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | 1) Determinar novo status
            |--------------------------------------------------------------------------
            */
            $providerStatus = strtolower(data_get($data, 'status', 'pending'));

            $mappedStatus = match ($providerStatus) {
                'paid', 'completed', 'approved' => 'paid',
                'failed', 'rejected', 'refused', 'cancelled', 'canceled' => 'failed',
                default => 'processing',
            };

            /*
            |--------------------------------------------------------------------------
            | 2) E2E - usar do provider ou gerar um interno
            |--------------------------------------------------------------------------
            */
            $e2e = data_get($data, 'e2e_id');

            if (empty($e2e) && $mappedStatus === 'paid') {
                $e2e = 'E2E' . now()->format('YmdHis') . strtoupper(Str::random(8));
                Log::warning('⚠️ E2E interno gerado (Pluggou não enviou)', [
                    'withdraw_id' => $withdraw->id,
                    'generated_e2e' => $e2e,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Determinar hora do pagamento
            |--------------------------------------------------------------------------
            */
            $paidAtProvider = data_get($data, 'paid_at');
            $processedAt = $mappedStatus === 'paid'
                ? ($paidAtProvider ? Carbon::parse($paidAtProvider) : now())
                : $withdraw->processed_at;

            /*
            |--------------------------------------------------------------------------
            | 4) Atualizar saque — INCLUINDO processed_at!!! 🎉
            |--------------------------------------------------------------------------
            */
            $withdraw->update([
                'status'        => $mappedStatus,
                'processed_at'  => $processedAt,
                'meta' => array_merge($withdraw->meta ?? [], [
                    'e2e'              => $e2e,
                    'pluggou_webhook'  => $data,
                ]),
            ]);

            Log::info('✅ Saque atualizado via Webhook Pluggou PIXOUT', [
                'withdraw_id'  => $withdraw->id,
                'status'       => $mappedStatus,
                'processed_at' => $processedAt,
                'e2e'          => $e2e,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 5) Enviar webhook OUT (para seu parceiro)
            |--------------------------------------------------------------------------
            */
            $user = $withdraw->user;

            if ($user && $user->webhook_enabled && $user->webhook_out_url) {
                SendWebhookWithdrawUpdatedJob::dispatch(
                    $user->id,
                    $withdraw->id,
                    strtoupper($mappedStatus),
                    $providerId,
                    $data
                )->onQueue('webhooks');
            }

            /*
            |--------------------------------------------------------------------------
            | 6) Retorno
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success'      => true,
                'withdraw_id'  => $withdraw->id,
                'status'       => $mappedStatus,
                'processed_at' => $processedAt,
                'e2e'          => $e2e,
            ]);

        } catch (\Throwable $e) {
            Log::error('🚨 Erro ao processar Webhook Pluggou PIXOUT', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
