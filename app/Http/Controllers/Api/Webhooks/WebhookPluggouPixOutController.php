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
                Log::warning('⚠️ Saque não encontrado para provider_reference', [
                    'id' => $providerId
                ]);
                return response()->json(['error' => 'withdraw_not_found'], 404);
            }

            // Evita reprocessar conclusões
            if (in_array(strtolower($withdraw->status), ['paid', 'failed'], true)) {
                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | 1) STATUS vindo do provider
            |--------------------------------------------------------------------------
            */
            $providerStatus = strtolower(data_get($data, 'status', 'processing'));

            $mappedStatus = match ($providerStatus) {
                'paid', 'completed', 'approved' => 'paid',
                default => 'failed', // QUALQUER status diferente de PAID vira FAILED
            };

            /*
            |--------------------------------------------------------------------------
            | 2) ESTORNAR IMEDIATAMENTE QUALQUER STATUS != PAID
            |--------------------------------------------------------------------------
            */
            if ($mappedStatus !== 'paid') {

                Log::warning('💸 Pluggou reportou falha — estornando saldo', [
                    'withdraw_id' => $withdraw->id,
                    'provider_status' => $providerStatus,
                ]);

                // Usa o método oficial de estorno
                app(\App\Services\Withdraw\WithdrawService::class)
                    ->refundLocal($withdraw, "Falha no PIXOUT via Pluggou ({$providerStatus})");

                return response()->json([
                    'success'     => true,
                    'withdraw_id' => $withdraw->id,
                    'status'      => 'failed',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3) STATUS = PAID → Concluir normalmente
            |--------------------------------------------------------------------------
            */
            $e2e = data_get($data, 'e2e_id');

            if (empty($e2e)) {
                $e2e = 'E2E' . now()->format('YmdHis') . strtoupper(Str::random(8));
                Log::warning('⚠️ E2E interno gerado (Pluggou não enviou)', [
                    'withdraw_id' => $withdraw->id,
                    'generated_e2e' => $e2e,
                ]);
            }

            $paidAtProvider = data_get($data, 'paid_at');
            $processedAt = $paidAtProvider ? Carbon::parse($paidAtProvider) : now();

            $withdraw->update([
                'status'        => 'paid',
                'processed_at'  => $processedAt,
                'meta' => array_merge($withdraw->meta ?? [], [
                    'e2e'             => $e2e,
                    'pluggou_webhook' => $data,
                ]),
            ]);

            Log::info('✅ Saque confirmado como PAID via Pluggou', [
                'withdraw_id' => $withdraw->id,
                'processed_at' => $processedAt,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 4) Webhook OUT para o parceiro
            |--------------------------------------------------------------------------
            */
            $user = $withdraw->user;

            if ($user && $user->webhook_enabled && $user->webhook_out_url) {
                SendWebhookWithdrawUpdatedJob::dispatch(
                    $user->id,
                    $withdraw->id,
                    'PAID',
                    $providerId,
                    $data
                )->onQueue('webhooks');
            }

            return response()->json([
                'success'      => true,
                'withdraw_id'  => $withdraw->id,
                'status'       => 'paid',
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
