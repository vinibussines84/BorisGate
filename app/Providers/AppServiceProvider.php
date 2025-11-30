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
        // ⚡ Pré-carregamento do Vite
        Vite::prefetch(concurrency: 3);

        // 👀 Observador de transações
        Transaction::observe(TransactionObserver::class);

        // 🔐 Libera acesso ao Pulse apenas para o seu e-mail
        Gate::define('viewPulse', function (User $user) {
            return in_array($user->email, [
                'hubsend7@gmail.com',
            ]);
        });

        // 🔐 Libera acesso ao Horizon apenas para o seu e-mail
        Gate::define('viewHorizon', function ($user = null) {
            return in_array(optional($user)->email, [
                'hubsend7@gmail.com',
            ]);
        });
    }
}
