<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        $isAdmin = $u && (
            ($u->is_admin ?? false) === true ||
            ($u->role ?? null) === 'admin' ||
            (is_array($u->permissions ?? null) && in_array('admin', $u->permissions, true))
        );

        if (!$isAdmin) {
            abort(403, 'Acesso restrito a administradores.');
        }

        return $next($request);
    }
}
