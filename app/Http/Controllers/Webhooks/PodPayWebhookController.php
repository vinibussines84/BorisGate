<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PodPayWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        Log::info('PODPAY WEBHOOK RECEIVED', [
            'payload' => $request->all()
        ]);

        return response()->json(['ok' => true]);
    }
}
