<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashrashOne
{
    /**
     * Permite acesso apenas a usuários com dashrash == 1.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Se não estiver autenticado, deixa o middleware 'auth' cuidar.
        if (! $user) {
            return $next($request);
        }

        // Verifica se dashrash é diferente de 1
        if ((int) ($user->dashrash ?? 0) !== 1) {
            abort(403, 'Acesso negado: sua conta não possui permissão para este painel.');
        }

        return $next($request);
    }
}
