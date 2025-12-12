<?php

return [

    /*
    |--------------------------------------------------------------------------
    | XFlow – Credenciais de Autenticação
    |--------------------------------------------------------------------------
    |
    | client_id e client_secret fornecidos pela XFlow
    |
    */

    'client_id' => env('XFLOW_CLIENT_ID'),
    'client_secret' => env('XFLOW_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | XFlow – Token (cache)
    |--------------------------------------------------------------------------
    |
    | O token NÃO deve ser fixo.
    | Ele será salvo em cache automaticamente pelo service.
    |
    */

    'token_cache_key' => 'xflow_api_token',

    /*
    |--------------------------------------------------------------------------
    | XFlow – URLs
    |--------------------------------------------------------------------------
    */

    'base_url' => env('XFLOW_BASE_URL', 'https://api.xflowpayments.co'),
    'auth_endpoint' => '/api/auth/login',

    /*
    |--------------------------------------------------------------------------
    | XFlow – Timeout e Retry
    |--------------------------------------------------------------------------
    */

    'timeout' => env('XFLOW_TIMEOUT', 10),
    'retry_times' => 2,
    'retry_sleep' => 150,

    /*
    |--------------------------------------------------------------------------
    | XFlow – Webhook
    |--------------------------------------------------------------------------
    */

    'callback_url' => env(
        'XFLOW_CALLBACK_URL',
        'https://equitpay.app/api/webhooks/xflow'
    ),
];
