<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PodPayWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            // Captura o corpo bruto ou JSON
            $raw = $request->json()->all()
                ?: json_decode($request->getContent(), true)
                ?: [];

            // Log opcional (pode remover se quiser ZERO ação)
            Log::info("📩 Webhook PodPay recebido (modo silencioso)", [
                'payload' => $raw
            ]);

            // Retorna OK sem fazer nada
            return response()->json([
                'success' => true,
                'message' => 'Webhook recebido com sucesso (nenhuma ação executada).'
            ]);

        } catch (\Throwable $e) {

            Log::error("🚨 Erro ao processar webhook PodPay (modo silencioso)", [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'payload' => $request->getContent(),
            ]);

            return response()->json(['error' => 'internal_error'], 500);
        }
    }
}
