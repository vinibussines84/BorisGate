<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LumnisWithdrawController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $data = $request->json()->all();

            $externalRef = data_get($data, 'external_ref');
            $status      = strtoupper(data_get($data, 'status', ''));
            $amount      = data_get($data, 'amount');
            $identifier  = data_get($data, 'identifier');

            if (!$externalRef) {
                return response()->json(['error' => 'Missing external_ref'], 422);
            }

            /** @var Withdraw|null $withdraw */
            $withdraw = Withdraw::query()
                ->where('provider', 'lumnis')
                ->where('meta->external_id', $externalRef)
                ->first();

            if (!$withdraw) {
                Log::warning('Webhook Lumnis: saque não encontrado', [
                    'external_ref' => $externalRef,
                ]);
                return response()->json(['error' => 'Withdraw not found'], 404);
            }

            // Evita marcar novamente caso já esteja finalizado
            if (in_array($withdraw->status, ['success', 'failed'])) {
                return response()->json(['message' => 'Already processed']);
            }

            // Mapeia status Lumnis → status local
            $map = [
                'WITHDRAW_REQUEST' => 'processing',
                'PENDING'          => 'processing',
                'SUCCESS'          => 'success',
                'FAIL'             => 'failed',
                'FAILED'           => 'failed',
            ];

            $newStatus = $map[$status] ?? strtolower($status);

            $withdraw->update([
                'status' => $newStatus,
                'provider_reference' => $identifier ?? $withdraw->provider_reference,
                'meta' => array_merge((array) $withdraw->meta, [
                    'last_webhook' => $data,
                    'webhook_received_at' => now()->toDateTimeString(),
                ]),
            ]);

            // Retorno rápido para o provedor
            return response()->json(['received' => true, 'status' => $newStatus]);
        } catch (\Throwable $e) {
            Log::error('Erro ao processar webhook Lumnis Withdraw', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
