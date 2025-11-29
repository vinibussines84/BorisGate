<?php

use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

return [

    /*
    |--------------------------------------------------------------------------
    | Telescope Master Switch
    |--------------------------------------------------------------------------
    */
    'enabled' => env('TELESCOPE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Telescope Domain
    |--------------------------------------------------------------------------
    */
    'domain' => env('TELESCOPE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Path
    |--------------------------------------------------------------------------
    */
    'path' => env('TELESCOPE_PATH', 'telescope'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Storage Driver
    |--------------------------------------------------------------------------
    */
    'driver' => env('TELESCOPE_DRIVER', 'database'),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Queue
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION', 'redis'),
        'queue'      => env('TELESCOPE_QUEUE', 'telescope'),
        'delay'      => env('TELESCOPE_QUEUE_DELAY', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Route Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'web',
        'auth',          // força sessão autenticada
        Authorize::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed / Ignored Paths & Commands
    |--------------------------------------------------------------------------
    */
    'only_paths' => [
        // 'api/*'
    ],

    'ignore_paths' => [
        'api/ping',   // healthcheck
        'livewire*',
        'nova-api*',
        'pulse*',
        '_boost*',
    ],

    'ignore_commands' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Watchers
    |--------------------------------------------------------------------------
    */
    'watchers' => [
        Watchers\BatchWatcher::class => env('TELESCOPE_BATCH_WATCHER', true),

        Watchers\CacheWatcher::class => [
            'enabled' => env('TELESCOPE_CACHE_WATCHER', true),
            'hidden'  => [],
            'ignore'  => [],
        ],

        Watchers\ClientRequestWatcher::class => [
            'enabled'      => env('TELESCOPE_CLIENT_REQUEST_WATCHER', true),
            'ignore_hosts' => [],
        ],

        Watchers\CommandWatcher::class => [
            'enabled' => env('TELESCOPE_COMMAND_WATCHER', true),
            'ignore'  => [],
        ],

        Watchers\DumpWatcher::class => [
            'enabled' => env('TELESCOPE_DUMP_WATCHER', true),
            'always'  => env('TELESCOPE_DUMP_WATCHER_ALWAYS', false),
        ],

        Watchers\EventWatcher::class => [
            'enabled' => env('TELESCOPE_EVENT_WATCHER', true),
            'ignore'  => [],
        ],

        Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),

        Watchers\GateWatcher::class => [
            'enabled'          => env('TELESCOPE_GATE_WATCHER', true),
            'ignore_abilities' => [],
            'ignore_packages'  => true,
            'ignore_paths'     => [],
        ],

        Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),

        // ⬇️ Pegar todos os logs (debug)
        Watchers\LogWatcher::class => [
            'enabled' => env('TELESCOPE_LOG_WATCHER', true),
            'level'   => env('TELESCOPE_LOG_LEVEL', 'debug'),
        ],

        Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', true),

        Watchers\ModelWatcher::class => [
            'enabled'    => env('TELESCOPE_MODEL_WATCHER', true),
            'events'     => ['eloquent.*'],
            'hydrations' => true,
        ],

        Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', true),

        Watchers\QueryWatcher::class => [
            'enabled'         => env('TELESCOPE_QUERY_WATCHER', true),
            'ignore_packages' => true,
            'ignore_paths'    => [],
            'slow'            => env('TELESCOPE_SLOW_QUERY', 100), // ms
        ],

        Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),

        // ⬇️ Respostas maiores (KB) para inspecionar melhor payloads
        Watchers\RequestWatcher::class => [
            'enabled'             => env('TELESCOPE_REQUEST_WATCHER', true),
            'size_limit'          => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 256),
            'ignore_http_methods' => [],
            'ignore_status_codes' => [],
        ],

        Watchers\ScheduleWatcher::class => env('TELESCOPE_SCHEDULE_WATCHER', true),
        Watchers\ViewWatcher::class     => env('TELESCOPE_VIEW_WATCHER', true),
    ],
];
