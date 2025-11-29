<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DisableCsrfForApi
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/stric/*')) {
            // Evita qualquer tentativa de validar CSRF
            $request->headers->remove('X-CSRF-TOKEN');
            $request->headers->remove('X-XSRF-TOKEN');
            $request->attributes->set('csrf_skipped', true);
        }

        return $next($request);
    }
}
