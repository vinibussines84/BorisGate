<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class RememberSessionController extends Controller
{
    /**
     * Liga/desliga o "lembrar este dispositivo" baseado no token RPNet.
     * Requer usuário autenticado (mas NÃO usa Auth::login).
     *
     * Espera:
     *  - remember: bool (obrigatório)
     *  - duration_days: int (opcional, 1..365) — padrão 30
     *
     * Com remember=true:
     *  - Lê 'rpnet_token' da sessão atual
     *  - Cria cookie 'rpnet_remember' (criptografado pelo Laravel) com {uid, token, exp}
     *
     * Com remember=false:
     *  - Apaga cookie 'rpnet_remember'
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'remember'      => ['required', 'boolean'],
            'duration_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        if (! Auth::check()) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $user = $request->user();
        $cookieName = 'rpnet_remember';

        if ($data['remember'] === true) {
            // preciso do token ativo da RPNet na sessão
            $rpnetToken = session('rpnet_token');
            $rpnetExp   = session('rpnet_token_expiration'); // opcional

            if (empty($rpnetToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum token RPNet ativo na sessão para salvar.',
                ], 422);
            }

            // duração (padrão 30 dias)
            $days    = $data['duration_days'] ?? 30;
            $minutes = $days * 24 * 60;

            // payload salvo no cookie (Laravel vai criptografar por padrão)
            $payload = [
                'uid' => $user->getAuthIdentifier(),
                't'   => $rpnetToken,
                'exp' => now()->addDays($days)->timestamp,
            ];

            // Define cookie seguro (HttpOnly + SameSite=Lax). Domain/path padrão do app.
            Cookie::queue(cookie(
                $cookieName,
                base64_encode(json_encode($payload)),
                $minutes,            // minutes
                '/',                 // path
                null,                // domain (null = default)
                true,                // secure
                true,                // httpOnly
                false,               // raw
                'Lax'                // sameSite
            ));

            session([
                'remember_enabled_at' => now(),
                'remember_until'      => now()->addDays($days),
            ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Este dispositivo foi lembrado com base no token RPNet.',
                'remember' => true,
                'until'    => now()->addDays($days)->toIso8601String(),
            ]);
        }

        // Desligar: remove cookie e flags
        Cookie::queue(Cookie::forget($cookieName, '/', null, false, 'Lax'));

        session()->forget(['remember_enabled_at', 'remember_until']);

        return response()->json([
            'success'  => true,
            'message'  => 'Lembrar dispositivo desativado.',
            'remember' => false,
        ]);
    }
}
