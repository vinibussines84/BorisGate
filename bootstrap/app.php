<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Routing\Middleware\SubstituteBindings;
use App\Http\Middleware\HandleInertiaRequests;

// Middlewares custom
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureUserActive;
use App\Http\Middleware\ApiHeaderAuth;
use App\Http\Middleware\CheckUserStatus;

// Telescope
use Laravel\Telescope\Http\Middleware\Authorize as TelescopeAuthorize;

// Providers
use App\Providers\RateLimiterServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /**
         * 🌍 Global
         */
        $middleware->append(TrustProxies::class);

        /**
         * 🌐 Grupo WEB → ESSENCIAL para o Login funcionar
         * Aqui devem estar:
         * - Cookies (EncryptCookies)
         * - Session
         * - CSRF Token
         * - ShareErrorsFromSession
         * - Bindings
         */
        $middleware->web(prepend: [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            SubstituteBindings::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        /**
         * 🔓 Grupo API
         */
        $middleware->api(append: [
            SubstituteBindings::class,
        ]);

        /**
         * 🏷️ Aliases
         */
        $middleware->alias([
            'admin'             => EnsureAdmin::class,
            'ensure.active'     => EnsureUserActive::class,
            'api.header.auth'   => ApiHeaderAuth::class,
            'check.user.status' => CheckUserStatus::class,
            'telescope'         => TelescopeAuthorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withProviders([
        RateLimiterServiceProvider::class,
    ])
    ->create();
