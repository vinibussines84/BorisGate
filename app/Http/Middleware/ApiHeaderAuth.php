<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ApiHeaderAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Se já vier autenticado por Sanctum, segue o fluxo:
        if ($request->user()) {
            return $next($request);
        }

        $authkey   = $request->header('authkey')   ?? $request->header('X-Authkey');
        $secretkey = $request->header('secretkey') ?? $request->header('X-Secretkey');

        if (! $authkey || ! $secretkey) {
            return response()->json([
                'message' => 'Headers authkey e secretkey são obrigatórios.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = User::where('authkey', $authkey)->first();

        if (! $user) {
            return response()->json(['message' => 'Credenciais inválidas.'], Response::HTTP_UNAUTHORIZED);
        }

        $stored = (string) ($user->secretkey ?? '');

        // Se estiver hasheado (bcrypt/argon2), usa Hash::check. Senão, compara direto.
        $valid = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2')
            ? Hash::check($secretkey, $stored)
            : hash_equals($stored, (string) $secretkey);

        if (! $valid) {
            return response()->json(['message' => 'Credenciais inválidas.'], Response::HTTP_UNAUTHORIZED);
        }

        // Autentica a request (user resolver) e também a guarda "api" para policies/gates
        Auth::shouldUse('web'); // ou 'api' se você tiver guard próprio
        Auth::login($user);

        return $next($request);
    }
}
