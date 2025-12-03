<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Withdraw\WithdrawService;

class PodPayWithdrawWebhookController extends Controller
{
    public function __construct(
        private readonly WithdrawService $withdrawService
    ) {}

    public function __invoke(Request $request)
    {
        try {

            /**
             * -----------------------------------------------------
             * 1) Normaliza payload
             * -----------------------------------------------------
             */
            $payload = $request->json()->all()
                ?: json_decode($request->getContent(), true)
                ?: [];

            Log::info('📩 Webhook PodPay Withdraw recebido', [
                'payload' => $payload
            ]);

            /**
             * -----------------------------------------------------
             * 2) Extrair referencia do saque
             *    A PodPay SEMPRE envia `objectId`
             * -----------------------------------------------------
             */
            $providerId = data_get($payload, 'objectId');
            $data       = data_get($payload, 'data', []);

            if (!$providerId) {
                Log::warning('⚠️ Webhook PodPay Withdraw sem objectId.', [
                    'payload' => $payload
                ]);

                return response()->json(['error' => 'missing_reference'], 422);
            }

            /**
             * -----------------------------------------------------
             * 3) Mapear status PodPay → sistema
             * -----------------------------------------------------
             */
            $providerStatus = strtoupper(data_get($data, 'status', 'UNKNOWN'));

            $map = [
                'COMPLETED'        => 'paid',
                'PROCESSING'       => 'processing',
                'PENDING_ANALYSIS' => 'processing',
                'PENDING_QUEUE'    => 'processing',
                'CANCELLED'        => 'failed',
                'REFUSED'          => 'failed',
            ];

            $newStatus = $map[$providerStatus] ?? null;

            if (!$newStatus) {
                Log::info('ℹ️ Webhook PodPay ignorado — status não mapeado', [
                    'providerStatus' => $providerStatus
                ]);

                return response()->json(['success' => true, 'ignored' => true]);
            }

            /**
             * -----------------------------------------------------
             * 4) Enviar para WithdrawService (já trata tudo)
             * -----------------------------------------------------
             */
            $result = $this->withdrawService->handleWebhook([
                'provider_id' => $providerId,
                'provider_status' => $newStatus,
                'raw' => $payload,
            ]);

            /**
             * -----------------------------------------------------
             * 5) Retornos consistentes
             * -----------------------------------------------------
             */
            if (isset($result['paid'])) {
                return response()->json([
                    'success' => true,
                    'status'  => 'paid',
                ]);
            }

            if (isset($result['failed'])) {
                return response()->json([
                    'success' => true,
                    'status'  => 'failed',
                ]);
            }

            return response()->json([
                'success' => true,
                'ignored' => true,
            ]);

        } catch (\Throwable $e) {

            Log::error('🚨 Erro no Webhook PodPay Withdraw', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
