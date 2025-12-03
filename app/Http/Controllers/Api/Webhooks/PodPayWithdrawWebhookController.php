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
            $payload = $request->json()->all()
                ?: json_decode($request->getContent(), true)
                ?: [];

            Log::info('📩 Webhook PodPay Withdraw recebido', [
                'payload' => $payload
            ]);

            $providerId = (string) data_get($payload, 'objectId');
            $data       = data_get($payload, 'data', []);

            if (!$providerId) {
                return response()->json(['error' => 'missing_reference'], 422);
            }

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
                return response()->json(['success' => true, 'ignored' => true]);
            }

            $result = $this->withdrawService->handleWebhook([
                'provider_id'     => $providerId,
                'provider_status' => $newStatus,
                'raw'             => $payload,
            ]);

            return response()->json([
                'success' => true,
                'status'  => $newStatus,
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
