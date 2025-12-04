<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookWithdrawUpdatedJob;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookPluggouPixOutController extends Controller
{
    /**
     * Webhook Pluggou PIXOUT — Recebe notificações de saque.
     *
     * Exemplo de payload esperado:
     * {
     *   "id": "f47b180c-26c8-4404-a8d1-b2eb53afb465",
     *   "event_type": "withdrawal",
     *   "data": {
     *     "id": "f47b180c-26c8-4404-a8d1-b2eb53afb465",
     *     "status": "paid",
     *     "e2e_id": "E2E123...",
     *     "amount": 1300,
     *     "liquid_amount": 1280
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

            // Evita reprocessar se já estiver pago
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
            | 2) Gerar E2E se não vier da Pluggou
            |--------------------------------------------------------------------------
            */
            $e2e = data_get($data, 'e2e_id');

            if (empty($e2e) && $mappedStatus === 'paid') {
                $e2e = 'E2E' . now()->format('YmdHis') . strtoupper(Str::random(8));
                Log::warning('⚠️ Gerado E2E interno (faltante no webhook Pluggou)', [
                    'withdraw_id' => $withdraw->id,
                    'generated_e2e' => $e2e,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Atualizar saque
            |--------------------------------------------------------------------------
            */
            $withdraw->update([
                'status' => $mappedStatus,
                'meta' => array_merge($withdraw->meta ?? [], [
                    'e2e' => $e2e,
                    'pluggou_webhook' => $data,
                ]),
            ]);

            Log::info('✅ Saque atualizado via Webhook Pluggou PIXOUT', [
                'withdraw_id' => $withdraw->id,
                'status' => $mappedStatus,
                'e2e' => $e2e,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 4) Disparar webhook do sistema (para o parceiro)
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
            | 5) Retornar resposta
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'success' => true,
                'withdraw_id' => $withdraw->id,
                'status' => $mappedStatus,
                'e2e' => $e2e,
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
