<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookXFlowController extends Controller
{
    public function handle(Request $request)
    {
        // Apenas loga e devolve o payload recebido
        Log::info('XFLOW_WEBHOOK_RECEIVED', [
            'payload' => $request->all()
        ]);

        return response()->json([
            'status' => 'ok',
            'received' => $request->all(),
        ]);
    }
}
