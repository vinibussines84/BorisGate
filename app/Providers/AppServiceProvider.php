<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prefetch do Vite (mantido)
        Vite::prefetch(concurrency: 3);

        // Registro do Observer das transações
        Transaction::observe(TransactionObserver::class);

        // 🔐 Permitir acesso ao Pulse somente para o e-mail especificado
        Gate::define('viewPulse', function (User $user) {
            return in_array($user->email, [
                'hubsend7@gmail.com',
            ]);
        });
    }
}
