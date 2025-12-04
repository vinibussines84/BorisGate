<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookPluggouController extends Controller
{
    public function __invoke(Request $request)
    {
        Log::info('PLUGGOU_WEBHOOK_RECEIVED', [
            'ip'      => $request->ip(),
            'agent'   => $request->userAgent(),
            'payload' => $request->all(),
        ]);

        return response()->json(['success' => true]);
    }
}
