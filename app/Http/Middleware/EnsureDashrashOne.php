<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashrashOne
{
    /**
     * Permite acesso ao painel apenas para usuários com dashrash == 1.
     * Qualquer outro valor (0, 2, null, etc.) => 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Se não estiver logado, deixe o Authenticate do Filament redirecionar.
        if (! $user) {
            return $next($request);
        }

        if ((int) ($user->dashrash ?? 0) !== 1) {
            // Opcional: deslogar
            // auth()->logout();

            // Recusa com 403
            abort(403, 'Acesso negado: sua conta não possui permissão para este painel.');
        }

        return $next($request);
    }
}
