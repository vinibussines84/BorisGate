<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use App\Models\User;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // 🔔 Se quiser alertas, pode configurar aqui (exemplo):
        Horizon::routeMailNotificationsTo('hubsend7@gmail.com');
        // Horizon::routeSlackNotificationsTo('https://hooks.slack.com/...');
    }

    /**
     * Register the Horizon gate.
     *
     * Define quem pode acessar o Horizon em ambientes não-locais.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null) {
            return in_array(optional($user)->email, [
                'hubsend7@gmail.com',
            ]);
        });
    }
}
