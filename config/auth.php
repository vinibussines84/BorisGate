<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Define o guard e o broker de senha padrão da aplicação.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Cada guard define como os usuários serão autenticados.
    | O Filament usa um guard separado para evitar logout cruzado.
    |
    */

    'guards' => [
        // Guard padrão do site / Inertia
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Guard isolado para o painel Filament
        'filament' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Define como os usuários são obtidos do banco de dados.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reset de Senhas
    |--------------------------------------------------------------------------
    |
    | Configurações para recuperação de senha de usuários.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tempo de Confirmação de Senha
    |--------------------------------------------------------------------------
    |
    | Define o tempo (em segundos) até que a confirmação expire.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
