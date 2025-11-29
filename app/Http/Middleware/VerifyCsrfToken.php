<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * URIs que devem ser excluídas da verificação CSRF.
     *
     * Use isto apenas para:
     * - APIs internas consumidas por Axios/Fetch (como seu /api/withdraws)
     * - Webhooks externos (ex: Gateway, Pluggou, Horizon etc)
     *
     * A autenticação por sessão do Laravel CONTINUA funcionando normalmente.
     */
    protected $except = [
        // 🔓 Libera todas as rotas prefixadas com /api/
        // (ex.: /api/withdraws, /api/list/pix, /api/charges)
        'api/*',

        // 🔓 Libera reenvio manual de webhooks feitos via fetch()
        // (evita erro "CSRF token mismatch" no painel)
        'webhooks/resend/*',

        // Webhooks externos recebidos (ex: Horizon, Pluggou, GetPay, etc)
        // 'webhook/*',
    ];

    /**
     * Define se o cookie XSRF-TOKEN deve ser enviado.
     *
     * Isso ajuda frameworks como React/Vue/Inertia
     * quando você quiser trabalhar com CSRF opcional.
     */
    protected $addHttpCookie = true;
}
