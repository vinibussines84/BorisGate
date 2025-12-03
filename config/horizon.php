<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    */

    'name' => env('HORIZON_NAME', 'Horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    */

    'domain' => env('HORIZON_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    */

    'use' => env('HORIZON_REDIS_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    */

    'waits' => [
        'redis:default'   => 60,
        'redis:webhooks'  => 60,
        'redis:withdraws' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    */

    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs / Tags
    |--------------------------------------------------------------------------
    */

    'silenced' => [],
    'silenced_tags' => [],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */

    'fast_termination' => true,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit
    |--------------------------------------------------------------------------
    */

    'memory_limit' => 256,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    */

    'defaults' => [

        /*
        |--------------------------------------------------------------------------
        | 🟢 SUPERVISOR DEFAULT (fila normal)
        |--------------------------------------------------------------------------
        */
        'supervisor-default' => [
            'connection' => 'redis',
            'queue'      => ['default'],
            'balance'    => 'auto',
            'autoScalingStrategy' => 'time',

            'maxProcesses' => 10,
            'minProcesses' => 1,

            'maxTime'   => 0,
            'maxJobs'   => 0,
            'memory'    => 256,
            'tries'     => 3,
            'timeout'   => 90,
            'nice'      => 0,
        ],

        /*
        |--------------------------------------------------------------------------
        | 🔵 SUPERVISOR WEBHOOKS (fila exclusiva)
        |--------------------------------------------------------------------------
        */
        'supervisor-webhooks' => [
            'connection' => 'redis',
            'queue'      => ['webhooks'],
            'balance'    => 'auto',
            'autoScalingStrategy' => 'time',

            'maxProcesses' => 8,
            'minProcesses' => 1,

            'maxTime'   => 0,
            'maxJobs'   => 0,
            'memory'    => 256,
            'tries'     => 3,
            'timeout'   => 120,
            'nice'      => 0,
        ],

        /*
        |--------------------------------------------------------------------------
        | 🟣 SUPERVISOR WITHDRAWS (fila exclusiva)
        |--------------------------------------------------------------------------
        */
        'supervisor-withdraws' => [
            'connection' => 'redis',
            'queue'      => ['withdraws'],   // 🔥 AGORA A FILA EXISTE
            'balance'    => 'auto',
            'autoScalingStrategy' => 'time',

            'maxProcesses' => 5,
            'minProcesses' => 1,

            'maxTime'   => 0,
            'maxJobs'   => 0,
            'memory'    => 256,
            'tries'     => 3,
            'timeout'   => 120,
            'nice'      => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Configuration
    |--------------------------------------------------------------------------
    */

    'environments' => [

        'production' => [

            'supervisor-default' => [
                'maxProcesses'    => 15,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],

            'supervisor-webhooks' => [
                'maxProcesses'    => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],

            'supervisor-withdraws' => [
                'maxProcesses'    => 8,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [

            'supervisor-default' => [
                'maxProcesses' => 3,
            ],

            'supervisor-webhooks' => [
                'maxProcesses' => 2,
            ],

            'supervisor-withdraws' => [
                'maxProcesses' => 2,
            ],
        ],
    ],
];
