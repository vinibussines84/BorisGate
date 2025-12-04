<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookCoffePayController extends Controller
{
    public function handle(Request $request)
    {
        Log::info("COFFE_PAY_WEBHOOK_RECEIVED", [
            'payload' => $request->all(),
            'headers' => $request->headers->all()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook recebido.'
        ]);
    }
}
