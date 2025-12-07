<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\Withdraw\WithdrawService;

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
            | 2) Encontrar o saque pelo UUID (provider_reference)
            |--------------------------------------------------------------------------
            */
            $withdraw =
                Withdraw::where('provider_reference', $payload['uuid'])->first()
                ?? Withdraw::where('external_id', $payload['externalId'] ?? null)->first();

            if (!$withdraw) {

                Log::warning('⚠️ Saque não encontrado ao processar webhook GETPAY', [
                    'uuid'       => $payload['uuid'] ?? null,
                    'externalId' => $payload['externalId'] ?? null,
                ]);

                return response()->json(['error' => 'withdraw_not_found'], 404);
            }

            // Evita reprocessamento
            if (in_array($withdraw->status, ['paid', 'failed'], true)) {
                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Status do provider
            |--------------------------------------------------------------------------
            */
            $providerStatus = strtolower($payload['status'] ?? 'pending');

            $mappedStatus = match ($providerStatus) {
                'paid', 'completed', 'approved', 'success' => 'paid',
                default => 'failed',
            };

            /*
            |--------------------------------------------------------------------------
            | 4) Se não for pago → estornar
            |--------------------------------------------------------------------------
            */
            if ($mappedStatus !== 'paid') {

                Log::warning('💸 GETPAY reportou falha — estornando saldo', [
                    'withdraw_id'    => $withdraw->id,
                    'provider_status' => $providerStatus,
                ]);

                app(WithdrawService::class)->refundLocal(
                    $withdraw,
                    "Falha no PIXOUT via GetPay ({$providerStatus})"
                );

                return response()->json([
                    'success'     => true,
                    'withdraw_id' => $withdraw->id,
                    'status'      => 'failed',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 5) PAID → processar corretamente
            |--------------------------------------------------------------------------
            */
            $e2e = $payload['endToEndId'] ?? null;

            if (!$e2e) {
                $e2e = 'E2E' . now()->format('YmdHis') . strtoupper(Str::random(8));
                Log::warning('⚠️ E2E interno gerado', [
                    'withdraw_id' => $withdraw->id,
                    'generated_e2e' => $e2e,
                ]);
            }

            $paidAtProvider = $payload['processed_at'] ?? null;
            $processedAt = $paidAtProvider ? Carbon::parse($paidAtProvider) : now();

            /*
            |--------------------------------------------------------------------------
            | 6) USAR O WithdrawService CORRETAMENTE
            |--------------------------------------------------------------------------
            */
            app(WithdrawService::class)->markAsPaid(
                withdraw: $withdraw,
                payload: $payload,
                extra: [
                    'e2e'       => $e2e,
                    'paid_at'   => $processedAt,
                    'webhook'   => $payload,
                ]
            );

            Log::info('✅ Saque confirmado como PAID via GETPAY', [
                'withdraw_id'  => $withdraw->id,
                'processed_at' => $processedAt,
            ]);

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
