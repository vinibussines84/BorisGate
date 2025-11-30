<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Para onde redirecionar usuários após login.
     */
    public const HOME = '/dashboard';

    /**
     * Registra quaisquer serviços de roteamento / rate limiting.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configura os limitadores de taxa (RateLimiter).
     */
    protected function configureRateLimiting(): void
    {
        // 🔐 Limiter apenas para LOGIN
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));
            $ip    = $request->ip();
            $key   = $email.'|'.$ip;

            return [
                Limit::perMinute(5)->by($key)->response(function () {
                    return response()->json([
                        'message' => 'Muitas tentativas de login. Tente novamente em instantes.',
                    ], 429);
                }),
            ];
        });

        // 🚀 SEM LIMITES PARA TODA API
        RateLimiter::for('api', function (Request $request) {
            return Limit::none();
        });
    }
}
