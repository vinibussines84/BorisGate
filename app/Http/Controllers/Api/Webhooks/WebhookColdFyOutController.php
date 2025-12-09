<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookColdFyOutController extends Controller
{
    /**
     * Recebe webhooks de saques (cashouts) enviados pela ColdFy.
     * Apenas registra o conteúdo e confirma o recebimento.
     */
    public function handle(Request $request)
    {
        // Garante que o corpo é JSON válido
        if (!$request->isJson()) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid content type. Expected JSON.',
            ], 415);
        }

        $payload = $request->json()->all();

        // Loga headers e payload completo para debug
        Log::channel('webhooks')->info('📬 Webhook ColdFy OUT recebido', [
            'received_at' => now()->toIso8601String(),
            'headers'     => $request->headers->all(),
            'payload'     => $payload,
        ]);

        // Retorna resposta de confirmação sem alterar nada localmente
        return response()->json([
            'success'   => true,
            'message'   => 'Webhook ColdFy OUT recebido com sucesso.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
