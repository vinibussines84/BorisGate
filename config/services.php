<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Serviços Externos da Aplicação
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'kyc' => [
        'endpoint' => env('KYC_ENDPOINT', 'http://127.0.0.1:8001'),
    ],

    /*
    |--------------------------------------------------------------------------
    | COFFE PAY (antigo provedor)
    |--------------------------------------------------------------------------
    */

    'coffepay' => [
        'url'           => env('COFFE_PAY_URL', 'https://api.coffepay.com/api'),
        'client_id'     => env('COFFE_PAY_CLIENT_ID'),
        'client_secret' => env('COFFE_PAY_CLIENT_SECRET'),
        'timeout'       => env('COFFE_PAY_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | PLUGGOU (Novo Provedor de Pagamentos)
    |--------------------------------------------------------------------------
    */

    'pluggou' => [
        'public_key' => env('PLUGGOU_PUBLIC_KEY'),
        'secret_key' => env('PLUGGOU_SECRET_KEY'),
        'api_url'    => env('PLUGGOU_API_URL', 'https://api.pluggoutech.com/api'),
        'timeout'    => env('PLUGGOU_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | GETPAY (Voluti) — Legacy JWT Provider
    |--------------------------------------------------------------------------
    */

    'getpay' => [
        'api_url'  => env('GETPAY_API_URL', 'https://hub.getpay.one/api'),
        'email'    => env('GETPAY_EMAIL'),
        'password' => env('GETPAY_PASSWORD'),
        'timeout'  => env('GETPAY_TIMEOUT', 15),
    ],

];
