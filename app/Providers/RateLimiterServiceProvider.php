<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class RateLimiterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // throttle:login (mantenho porque é importante)
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower($request->input('email') ?? 'guest');

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(10)->by('ip:'.$request->ip()),
            ];
        });

        // 🚀 SEM LIMITES PARA A API
        RateLimiter::for('api', function (Request $request) {
            return Limit::none();
        });
    }
}
