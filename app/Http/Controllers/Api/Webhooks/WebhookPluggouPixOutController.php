<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookPluggouPixOutController extends Controller
{
    /**
     * Recebe notificações de saques (PIX Out) enviadas pela Pluggou.
     * Por enquanto, apenas registra os dados e confirma o recebimento.
     */
    public function __invoke(Request $request)
    {
        try {
            $payload = $request->json()->all();

            Log::info('📤 Webhook Pluggou PIX OUT recebido', [
                'payload' => $payload,
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
            ]);

            // Futuramente:
            // - Validar assinatura (se a Pluggou enviar algum cabeçalho de segurança)
            // - Localizar o registro do saque (Withdraw) pelo provider_reference
            // - Atualizar o status (paid / failed / canceled)
            // - Disparar o webhook para o cliente final (SendWebhookWithdrawUpdatedJob)

            return response()->json([
                'success' => true,
                'message' => 'Webhook recebido com sucesso (PIX Out).',
            ]);

        } catch (\Throwable $e) {
            Log::error('🚨 Erro ao processar webhook Pluggou PIX OUT', [
                'erro' => $e->getMessage(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar o webhook.',
            ], 500);
        }
    }
}
