<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LumnisWithdrawController extends Controller
{
    public function __invoke(Request $request)
    {
        // Captura o corpo JSON do webhook enviado pela Lumnis
        $data = $request->json()->all();

        // Apenas confirma o recebimento para o provedor
        return response()->json([
            'received' => true,
            'timestamp' => now()->toIso8601String(),
            'body' => $data,
        ], 200);
    }
}
