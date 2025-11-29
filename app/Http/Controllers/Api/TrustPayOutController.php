<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrustPayOutController extends Controller
{
    /**
     * Recebe requisições de criação de saque (apenas ecoa o JSON).
     * Não grava em banco e não chama nenhum service.
     */
    public function store(Request $request)
    {
        $raw     = $request->getContent();
        $json    = $request->json()->all() ?: [];
        $headers = collect($request->headers->all())
            ->map(fn ($v) => is_array($v) && count($v) === 1 ? $v[0] : $v)
            ->toArray();

        $meta = [
            'method'     => $request->method(),
            'ip'         => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'path'       => $request->path(),
            'query'      => $request->query(),
            'received_at'=> now()->toIso8601String(),
        ];

        // Log seguro do payload
        Log::info('TrustPayOutController@store received JSON', [
            'meta'    => $meta,
            'headers' => $headers,
            'json'    => $json,
            'raw_len' => strlen($raw ?: ''),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'store: JSON recebido com sucesso (modo eco).',
            'meta'    => $meta,
            'headers' => $headers,
            'json'    => $json,
            'raw'     => $raw,
        ], 200);
    }

    /**
     * Webhook de PAYOUT (apenas ecoa o JSON).
     * Não altera status, não estorna e não chama nenhum service.
     */
    public function webhookPayout(Request $request)
    {
        $raw     = $request->getContent();
        $json    = $request->json()->all() ?: [];
        $headers = collect($request->headers->all())
            ->map(fn ($v) => is_array($v) && count($v) === 1 ? $v[0] : $v)
            ->toArray();

        $meta = [
            'method'     => $request->method(),
            'ip'         => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'path'       => $request->path(),
            'query'      => $request->query(),
            'received_at'=> now()->toIso8601String(),
        ];

        Log::info('TrustPayOutController@webhookPayout received JSON', [
            'meta'    => $meta,
            'headers' => $headers,
            'json'    => $json,
            'raw_len' => strlen($raw ?: ''),
        ]);

        // 200 OK para provedores que exigem sucesso síncrono;
        // troque para 202 se preferir "Accepted".
        return response()->json([
            'ok'      => true,
            'message' => 'webhookPayout: JSON recebido com sucesso (modo eco).',
            'meta'    => $meta,
            'headers' => $headers,
            'json'    => $json,
            'raw'     => $raw,
        ], 200);
    }
}
