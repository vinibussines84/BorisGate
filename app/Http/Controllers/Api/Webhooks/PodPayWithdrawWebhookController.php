<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PodPayWithdrawWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        Log::info('📩 Webhook PodPay recebido', [
            'payload' => $request->all(),
            'headers' => $request->headers->all()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook recebido com sucesso.'
        ]);
    }
}
