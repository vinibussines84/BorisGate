<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Autenticação do Filament
    |--------------------------------------------------------------------------
    |
    | Define o guard que o Filament usará, separado da aplicação principal.
    | O painel usa o login nativo do Filament e o guard "filament"
    | (declarado no config/auth.php).
    |
    */

    'auth' => [
        'guard' => 'filament',
        'pages' => [
            'login' => \Filament\Pages\Auth\Login::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações adicionais do Filament
    |--------------------------------------------------------------------------
    |
    | Estas opções são complementares e podem ser usadas para controle de
    | notificações, broadcast e comportamento de transações.
    |
    */

    'passwords' => 'users',

    'broadcasting' => false,

    'database_notifications' => true,

    'db_transactional_saves' => true,

];
