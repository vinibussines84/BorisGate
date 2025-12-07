<?php

namespace App\Http\Controllers\Api\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class WebhookCnInController extends Controller
{
    public function handle(Request $request)
    {
        // ✔ Garantir que o conteúdo seja JSON
        if (!$request->isJson()) {
            return response()->json([
                'error' => 'Invalid content type. JSON expected.'
            ], 415); // 415 Unsupported Media Type
        }

        $payload = $request->json()->all();

        Log::channel('webhooks')->info('📥 Webhook CN IN recebido', [
            'payload' => $payload
        ]);

        // TODO: lógica da sua integração
        return response()->json([
            'status'  => 'success',
            'message' => 'CN IN webhook received',
        ]);
    }
}
