<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RestrictApiAccessByIp
{
    public function handle(Request $request, Closure $next): Response
    {
        /**
         * Detecta IP real mesmo por trás do Cloudflare.
         * CF-Connecting-IP → IP original do visitante
         * X-Forwarded-For → sequência de proxies (último é o visitante)
         */
        $clientIp = $request->headers->get('CF-Connecting-IP')
            ?? explode(',', $request->headers->get('X-Forwarded-For'))[0]
            ?? $request->ip();

        // ⚙️ Lista de IPs permitidos
        $allowedIps = array_filter(array_map('trim', explode(',', (string) env('ALLOWED_API_IPS', '127.0.0.1,::1,164.90.136.78'))));

        // 🌐 Lista de domínios/origens permitidos
        $allowedOrigins = array_filter(array_map('trim', explode(',', (string) env('ALLOWED_API_ORIGINS', 'https://corbnacario-yfjdd3tp.on-forge.com'))));

        $origin  = $request->headers->get('Origin');
        $referer = $request->headers->get('Referer');

        // 🚫 Bloqueia IP não autorizado
        if (!in_array($clientIp, $allowedIps, true)) {
            Log::warning('🚫 Acesso bloqueado por IP', [
                'ip_detectado' => $clientIp,
                'ip_laravel'   => $request->ip(),
                'url'          => $request->fullUrl(),
                'headers'      => [
                    'CF-Connecting-IP' => $request->headers->get('CF-Connecting-IP'),
                    'X-Forwarded-For'  => $request->headers->get('X-Forwarded-For'),
                ],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Acesso negado. IP não autorizado.',
                'your_ip' => $clientIp,
            ], 403);
        }

        // 🚫 Bloqueia origem ou referer inválido
        if ($origin && !$this->originIsAllowed($origin, $allowedOrigins)) {
            Log::warning('🚫 Origem bloqueada', ['origin' => $origin, 'ip' => $clientIp]);
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado. Origem inválida.',
                'origin'  => $origin,
            ], 403);
        }

        if ($referer && !$this->originIsAllowed($referer, $allowedOrigins)) {
            Log::warning('🚫 Referer bloqueado', ['referer' => $referer, 'ip' => $clientIp]);
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado. Referer inválido.',
                'referer' => $referer,
            ], 403);
        }

        return $next($request);
    }

    private function originIsAllowed(string $url, array $allowedOrigins): bool
    {
        foreach ($allowedOrigins as $allowed) {
            if (str_starts_with($url, $allowed)) {
                return true;
            }
        }
        return false;
    }
}
