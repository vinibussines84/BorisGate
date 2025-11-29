<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureRpnetTokenIsValid
{
    public function handle(Request $request, Closure $next)
    {
        $token     = session('rpnet_token');
        $expiresAt = session('rpnet_token_expiration');

        if (!$token || !$expiresAt || now()->greaterThan($expiresAt)) {
            session()->forget(['rpnet_token', 'rpnet_token_expiration', 'rpnet_login', 'rpnet_balance']);
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
            session()->forget('url.intended');

            return response()->json([
                'success'  => false,
                'message'  => 'Sessão expirada. Faça login novamente.',
                'redirect' => route('login'),
            ], 401);
        }

        return $next($request);
    }
}
