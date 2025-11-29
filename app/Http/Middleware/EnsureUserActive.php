<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();

        // Só valida se houver usuário autenticado
        if ($u) {
            $status    = (string) ($u->user_status ?? 'ativo'); // 'ativo' | 'inativo' | 'bloqueado'
            $blocked   = (bool) ($u->is_blocked ?? false);
            $isActive  = ($status === 'ativo') && ! $blocked;

            if (! $isActive) {
                // finaliza sessão e invalida tokens
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Define mensagem adequada
                $title = $status === 'bloqueado' || $blocked
                    ? 'Conta bloqueada'
                    : 'Conta inativa';

                $message = $status === 'bloqueado' || $blocked
                    ? 'Seu acesso foi bloqueado. Entre em contato com o suporte.'
                    : 'Sua conta está inativa no momento. Fale com o suporte para reativar.';

                // 423 Locked combina bem com casos de bloqueio/hold
                return response()->view('errors.user-hold', [
                    'title'   => $title,
                    'message' => $message,
                    'status'  => $status,
                ], 423);
            }
        }

        return $next($request);
    }
}
