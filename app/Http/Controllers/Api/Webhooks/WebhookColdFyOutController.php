<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Withdraw;
use App\Services\Withdraw\WithdrawService;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WebhookColdFyOutController extends Controller
{
    /**
     * Webhook para notificações de saques (cashouts) enviados pela ColdFy.
     */
    public function handle(Request $request)
    {
        try {
            // 🔒 Garante que o corpo é JSON
            if (!$request->isJson()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Invalid content type. Expected JSON.',
                ], 415);
            }

            $payload = $request->json()->all();

            Log::channel('webhooks')->info('📬 Webhook COLDFY PIXOUT recebido', [
                'received_at' => now()->toIso8601String(),
                'headers'     => $request->headers->all(),
                'payload'     => $payload,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 1) Validar estrutura mínima
            |--------------------------------------------------------------------------
            */
            $withdrawData = data_get($payload, 'withdrawal');
            $providerId   = data_get($withdrawData, 'id');

            if (!$withdrawData || !$providerId) {
                Log::warning('⚠️ Payload inválido no webhook ColdFy PIXOUT', [
                    'payload' => $payload
                ]);
                return response()->json(['error' => 'invalid_payload'], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | 2) Encontrar saque
            |--------------------------------------------------------------------------
            */
            $withdraw = Withdraw::where('provider_reference', $providerId)->first();

            if (!$withdraw) {
                Log::warning('⚠️ Saque não encontrado ao processar webhook ColdFy', [
                    'provider_id' => $providerId,
                ]);

                return response()->json(['error' => 'withdraw_not_found'], 404);
            }

            // Ignorar se já finalizado
            if (in_array($withdraw->status, ['paid', 'failed'], true)) {
                Log::info('ℹ️ Webhook ColdFy ignorado — saque já finalizado', [
                    'withdraw_id' => $withdraw->id,
                    'status'      => $withdraw->status,
                ]);
                return response()->json(['ignored' => true]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Verificar status do provedor
            |--------------------------------------------------------------------------
            */
            $providerStatus = strtolower(data_get($withdrawData, 'status', 'pending'));

            $mappedStatus = match ($providerStatus) {
                'approved' => 'paid',
                default    => 'failed',
            };

            /*
            |--------------------------------------------------------------------------
            | 4) Se não for approved → estorna o saldo
            |--------------------------------------------------------------------------
            */
            if ($mappedStatus !== 'paid') {
                Log::warning('💸 COLDFY reportou falha — estornando saldo', [
                    'withdraw_id'     => $withdraw->id,
                    'provider_status' => $providerStatus,
                ]);

                app(WithdrawService::class)->refundLocal(
                    $withdraw,
                    "Falha no PIXOUT via ColdFy ({$providerStatus})"
                );

                return response()->json([
                    'success'     => true,
                    'withdraw_id' => $withdraw->id,
                    'status'      => 'failed',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 5) Se aprovado → marcar como pago
            |--------------------------------------------------------------------------
            */
            $e2e = 'E2E' . now()->format('YmdHis') . strtoupper(Str::random(8));
            $paidAtProvider = data_get($withdrawData, 'updated_at') ?? data_get($withdrawData, 'created_at');
            $processedAt = $paidAtProvider ? Carbon::parse($paidAtProvider) : now();

            app(WithdrawService::class)->markAsPaid(
                withdraw: $withdraw,
                payload: $payload,
                extra: [
                    'e2e'       => $e2e,
                    'paid_at'   => $processedAt,
                    'webhook'   => $payload,
                ]
            );

            Log::info('✅ Saque confirmado como PAID via ColdFy', [
                'withdraw_id'  => $withdraw->id,
                'processed_at' => $processedAt,
                'e2e'          => $e2e,
            ]);

            return response()->json([
                'success'      => true,
                'withdraw_id'  => $withdraw->id,
                'status'       => 'paid',
                'processed_at' => $processedAt,
                'e2e'          => $e2e,
            ]);

        } catch (\Throwable $e) {
            Log::channel('webhooks')->error('🚨 Erro ao processar Webhook COLDFY PIXOUT', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
