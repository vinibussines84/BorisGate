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

            $payload = $request->json()->all();
            Log::info('📩 Webhook PodPay recebido', ['payload' => $payload]);

            /* ============================================================
             * 1️⃣ Extrair referência obrigatória
             * ============================================================ */
            $reference = (string) data_get($payload, 'objectId');

            if (!$reference) {
                Log::warning('⚠️ Webhook sem reference.');
                return response()->json(['error' => 'missing_reference'], 422);
            }

            /* ============================================================
             * 2️⃣ Processar webhook via WithdrawService
             * ============================================================ */
            $result = $this->withdrawService->handleWebhook($payload);

            /* ============================================================
             * 3️⃣ Retornos consistentes
             * ============================================================ */
            if (isset($result['failed'])) {
                return response()->json([
                    'success' => true,
                    'status'  => 'failed',
                ]);
            }

            if (isset($result['paid'])) {
                return response()->json([
                    'success' => true,
                    'status'  => 'paid',
                ]);
            }

            return response()->json([
                'success' => true,
                'ignored' => true,
            ]);

        } catch (\Throwable $e) {

            Log::error('🚨 Erro ao processar webhook PodPay', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
