<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CashtimeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Webhook Cashtime recebido', [
            'payload' => $request->all()
        ]);

        return response()->json(['success' => true]);
    }
}
