<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * Bloqueia o usuário se estiver com status = 'bloqueado'
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Se não estiver logado, segue normal
        if (!$user) {
            return $next($request);
        }

        // Se a rota atual for a página de bloqueio, não redireciona
        if ($request->routeIs('usuario.bloqueado')) {
            return $next($request);
        }

        // Permitir o logout
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        // Usuário bloqueado → redireciona
        if ($user->user_status === 'bloqueado') {
            return redirect()->route('usuario.bloqueado');
        }

        return $next($request);
    }
}
