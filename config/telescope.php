<?php

use Laravel\Telescope\Watchers;

return [

    /*
    |--------------------------------------------------------------------------
    | Toggle geral
    |--------------------------------------------------------------------------
    | Produção: defina TELESCOPE_ENABLED=true no .env
    */
    'enabled' => env('TELESCOPE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Domínio e path
    |--------------------------------------------------------------------------
    */
    'domain' => env('TELESCOPE_DOMAIN'),
    'path'   => env('TELESCOPE_PATH', 'telescope'),

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    */
    'driver' => env('TELESCOPE_DRIVER', 'database'),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk'      => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fila
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'queue'      => env('TELESCOPE_QUEUE', 'telescope'),
        'delay'      => env('TELESCOPE_QUEUE_DELAY', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware (NÃO adicionar auth aqui)
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'web',
        \Laravel\Telescope\Http\Middleware\Authorize::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Filtragem (CAPTURAR TUDO → não ignorar nada)
    |--------------------------------------------------------------------------
    */
    'only_paths' => [
        // captura tudo
    ],

    'ignore_paths' => [
        // removido para capturar absolutamente tudo
    ],

    'ignore_commands' => [
        // nenhum comando ignorado
    ],

    /*
    |--------------------------------------------------------------------------
    | Watchers (TODOS ATIVOS, CAPTURA TOTAL)
    |--------------------------------------------------------------------------
    */
    'watchers' => [

        Watchers\BatchWatcher::class => true,

        Watchers\CacheWatcher::class => [
            'enabled' => true,
            'hidden'  => [],
            'ignore'  => [],
        ],

        Watchers\ClientRequestWatcher::class => [
            'enabled'      => true,
            'ignore_hosts' => [],
        ],

        Watchers\CommandWatcher::class => [
            'enabled' => true,
            'ignore'  => [],
        ],

        Watchers\DumpWatcher::class => [
            'enabled' => true,
            'always'  => true,
        ],

        Watchers\EventWatcher::class => [
            'enabled' => true,
            'ignore'  => [],
        ],

        Watchers\ExceptionWatcher::class => true,

        Watchers\GateWatcher::class => [
            'enabled'          => true,
            'ignore_abilities' => [],
            'ignore_packages'  => false,
            'ignore_paths'     => [],
        ],

        Watchers\JobWatcher::class => true,

        Watchers\LogWatcher::class => [
            'enabled' => true,
            'level'   => 'debug',
        ],

        Watchers\MailWatcher::class => true,

        Watchers\ModelWatcher::class => [
            'enabled'    => true,
            'events'     => ['eloquent.*'],
            'hydrations' => true,
        ],

        Watchers\NotificationWatcher::class => true,

        Watchers\QueryWatcher::class => [
            'enabled'         => true,
            'ignore_packages' => false,
            'ignore_paths'    => [],
            'slow'            => 1, // captura TUDO como "slow"
        ],

        Watchers\RedisWatcher::class => true,

        Watchers\RequestWatcher::class => [
            'enabled'             => true,
            'size_limit'          => 1024 * 1024, // 1MB de limite
            'ignore_http_methods' => [],
            'ignore_status_codes' => [],
        ],

        Watchers\ScheduleWatcher::class => true,

        Watchers\ViewWatcher::class => true,
    ],
];
