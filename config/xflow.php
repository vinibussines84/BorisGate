<?php

return [

    /*
    |--------------------------------------------------------------------------
    | XFlow – Credenciais de Autenticação
    |--------------------------------------------------------------------------
    */

    'client_id'     => env('XFLOW_CLIENT_ID'),
    'client_secret' => env('XFLOW_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | XFlow – Token (cache)
    |--------------------------------------------------------------------------
    |
    | Token é obtido via /auth/login e armazenado em cache
    |
    */

    'token_cache_key' => 'xflow_api_token',

    /*
    |--------------------------------------------------------------------------
    | XFlow – URLs
    |--------------------------------------------------------------------------
    */

    'base_url'      => env('XFLOW_BASE_URL', 'https://api.xflowpayments.co'),
    'auth_endpoint' => '/api/auth/login',

    /*
    |--------------------------------------------------------------------------
    | XFlow – Timeout e Retry
    |--------------------------------------------------------------------------
    */

    'timeout'       => env('XFLOW_TIMEOUT', 10),
    'retry_times'   => env('XFLOW_RETRY_TIMES', 2),
    'retry_sleep'   => env('XFLOW_RETRY_SLEEP', 150),

    /*
    |--------------------------------------------------------------------------
    | XFlow – Webhooks (SEPARADOS)
    |--------------------------------------------------------------------------
    */

    // PIX IN / OUT
    'callback_url_pix' => env(
        'XFLOW_PIX_CALLBACK_URL',
        'https://equitpay.app/api/webhooks/xflow'
    ),

    // WITHDRAW (SAQUE)
    'callback_url_withdraw' => env(
        'XFLOW_WITHDRAW_CALLBACK_URL',
        'https://equitpay.app/api/webhooks/xflow/withdraw'
    ),
];
