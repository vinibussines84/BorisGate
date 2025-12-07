<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookWithdrawUpdatedJob;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WebhookCnOutController extends Controller
{
    public function handle(Request $request)
    {
        try {

            if (!$request->isJson()) {
                return response()->json(['error' => 'Invalid content type. JSON expected.'], 415);
            }

            $payload = $request->json()->all();

            Log::channel('webhooks')->info('📩 Webhook GETPAY PIXOUT recebido', [
                'payload' => $payload
            ]);

            /*
            |--------------------------------------------------------------------------
            | 1) Validar tipo do evento
            |--------------------------------------------------------------------------
            */
            if (($payload['type'] ?? null) !== 'PAYOUT_CONFIRMED') {
                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | 2) Encontrar o saque pelo provider_reference = UUID
            |--------------------------------------------------------------------------
            */
            $withdraw =
                Withdraw::where('provider_reference', $payload['uuid'])->first()
                ?? Withdraw::where('external_id', $payload['externalId'] ?? null)->first();

            if (!$withdraw) {
                Log::warning('⚠️ Saque não encontrado para GETPAY provider_reference', [
                    'uuid'       => $payload['uuid'] ?? null,
                    'externalId' => $payload['externalId'] ?? null,
                ]);

                return response()->json(['error' => 'withdraw_not_found'], 404);
            }

            // Evita reprocessamento
            if (in_array(strtolower($withdraw->status), ['paid', 'failed'], true)) {
                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Mapear STATUS
            |--------------------------------------------------------------------------
            */
            $providerStatus = strtolower($payload['status'] ?? 'pending');

            $mappedStatus = match ($providerStatus) {
                'paid', 'completed', 'approved', 'success' => 'paid',
                default => 'failed',
            };

            /*
            |--------------------------------------------------------------------------
            | 4) Se NÃO for paid → estornar imediatamente
            |--------------------------------------------------------------------------
            */
            if ($mappedStatus !== 'paid') {

                Log::warning('💸 GETPAY reportou falha — estornando saldo', [
                    'withdraw_id'    => $withdraw->id,
                    'provider_status' => $providerStatus,
                ]);

                app(\App\Services\Withdraw\WithdrawService::class)
                    ->refundLocal($withdraw, "Falha no PIXOUT via GetPay ({$providerStatus})");

                return response()->json([
                    'success'     => true,
                    'withdraw_id' => $withdraw->id,
                    'status'      => 'failed',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 5) STATUS = PAID → concluir igual Pluggou
            |--------------------------------------------------------------------------
            */
            $e2e = $payload['endToEndId'] ?? null;

            // Se a GetPay não enviar E2E → gera um interno igual Pluggou
            if (empty($e2e)) {
                $e2e = 'E2E' . now()->format('YmdHis') . strtoupper(Str::random(8));

                Log::warning('⚠️ E2E interno gerado (GETPAY não enviou)', [
                    'withdraw_id' => $withdraw->id,
                    'generated_e2e' => $e2e,
                ]);
            }

            $paidAtProvider = $payload['processed_at'] ?? null;
            $processedAt = $paidAtProvider ? Carbon::parse($paidAtProvider) : now();

            // Atualiza saque
            $withdraw->update([
                'status'        => 'paid',
                'processed_at'  => $processedAt,
                'meta' => array_merge($withdraw->meta ?? [], [
                    'e2e'            => $e2e,
                    'getpay_webhook' => $payload,
                ]),
            ]);

            Log::info('✅ Saque confirmado como PAID via GETPAY', [
                'withdraw_id'  => $withdraw->id,
                'processed_at' => $processedAt,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 6) Disparar webhook OUT para seu cliente — igual Pluggou
            |--------------------------------------------------------------------------
            */
            $user = $withdraw->user;

            if ($user && $user->webhook_enabled && $user->webhook_out_url) {
                SendWebhookWithdrawUpdatedJob::dispatch(
                    $user->id,
                    $withdraw->id,
                    'PAID',
                    $payload['uuid'], // provider_reference
                    $payload
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

            Log::error('🚨 Erro ao processar Webhook GETPAY PIXOUT', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
