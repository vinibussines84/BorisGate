<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookColdFyController extends Controller
{
    public function handle(Request $request)
    {
        // Loga todo o conteúdo recebido
        Log::info('📬 Webhook ColdFy recebido', [
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
        ]);

        // Apenas responde que foi recebido com sucesso
        return response()->json([
            'status'  => 'ok',
            'message' => 'Webhook ColdFy recebido com sucesso',
        ]);
    }
}
