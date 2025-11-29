<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        // 👉 CAPTURAR TUDO — SEM QUALQUER FILTRO
        Telescope::filter(function () {
            return true;
        });
    }

    /**
     * Esconde apenas dados sensíveis.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters([
            '_token', 'password', 'password_confirmation',
        ]);

        Telescope::hideRequestHeaders([
            'cookie', 'x-csrf-token', 'x-xsrf-token', 'authorization',
            'x-api-key', 'x-auth-key', 'x-secret-key', 'x-access-key',
        ]);
    }

    /**
     * Gate de acesso para ambientes não-locais.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            // 🔐 coloque os e-mails que podem acessar o Telescope
            return in_array($user->email, [
                'hubsend7@gmail.com',
            ], true);
        });
    }

    /**
     * Use o Gate acima
     */
    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(function () {
            return Gate::allows('viewTelescope');
        });
    }
}
