<?php

return [

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

    // ---------------------------------------
    // KYC local
    // ---------------------------------------
    'kyc' => [
        'endpoint' => env('KYC_ENDPOINT', 'http://127.0.0.1:8001'),
    ],

    // ---------------------------------------
    // VeltraxPay (cashout)
    // ---------------------------------------
    'veltrax' => [
        'base_url'      => env('VELTRAX_BASE_URL', 'https://api.veltraxpay.com'),
        'client_id'     => env('VELTRAX_CLIENT_ID'),
        'client_secret' => env('VELTRAX_CLIENT_SECRET'),
        'callback_url'  => env('VELTRAX_CALLBACK_URL', 'https://app.closedpay.com.br/api/webhooks/veltrax'),
        'timeout'       => env('WEBHOOK_TIMEOUT_SECONDS', 5),
    ],

    // ---------------------------------------
    // TrustIn Legacy
    // ---------------------------------------
    'trustin' => [
        'base_url'                 => env('TRUSTIN_BASE_URL', 'https://hub.getpay.one'),
        'email'                    => env('TRUSTIN_EMAIL'),
        'password'                 => env('TRUSTIN_PASSWORD'),
        'login_endpoint'           => env('TRUSTIN_LOGIN_ENDPOINT', '/api/login'),
        'create_payment_endpoint'  => env('TRUSTIN_CREATE_PAYMENT_ENDPOINT', '/api/create-payment'),
        'withdraw_endpoint'        => env('TRUSTIN_WITHDRAW_ENDPOINT', '/api/withdrawals'),
        'timeout'                  => env('TRUSTIN_TIMEOUT', 15),
        'connect_timeout'          => env('TRUSTIN_CONNECT_TIMEOUT', 5),
        'token_cache_key'          => env('TRUSTIN_TOKEN_CACHE_KEY', 'trustin.jwt.token'),
    ],

    // ---------------------------------------
    // Cashtime (PIX novo)
    // ---------------------------------------
    'cashtime' => [
        'base_url'   => env('CASHTIME_BASE_URL', 'https://api.cashtime.com.br'),
        'key'        => env('CASHTIME_KEY'),
        'timeout'    => env('CASHTIME_TIMEOUT', 15),
        'min_cents'  => env('CASHTIME_MIN_CENTS', 100),
        'fixed_cpf'  => env('CASHTIME_SUBSELLER_CPF', '12345678909'),
    ],

    // ---------------------------------------
    // Pluggou (PIX CASHIN)
    // ---------------------------------------
    'pluggou' => [
        'base_url'   => env('PLUGGOU_BASE_URL', 'https://api.pluggoutech.com/api'),
        'public_key' => env('PLUGGOU_PUBLIC_KEY'),
        'secret_key' => env('PLUGGOU_SECRET_KEY'),
        'timeout'    => env('PLUGGOU_TIMEOUT', 15),
    ],

    // ---------------------------------------
    // RAPDYN (PIX CASHIN)
    // ---------------------------------------
    'rapdyn' => [
        'base_url'   => env('RAPDYN_BASE_URL', 'https://app.rapdyn.io/api'),
        'token'      => env('RAPDYN_TOKEN'),
        'timeout'    => env('RAPDYN_TIMEOUT', 15),
    ],

    // ---------------------------------------
    // CASS PAGAMENTOS (PIX CASHIN)
    // ---------------------------------------
    'cass' => [
        'base_url'    => env('CASS_BASE_URL', 'https://api.casspagamentos.com/v1'),
        'public_key'  => env('CASS_PUBLIC_KEY'),
        'secret_key'  => env('CASS_SECRET_KEY'),
        'timeout'     => env('CASS_TIMEOUT', 15),
    ],

    // ---------------------------------------
    // REFLOWPAY (PIX CASHIN)
    // ---------------------------------------
    'reflowpay' => [
        'base_url' => env('REFLOWPAY_BASE_URL', 'https://cashin.safepayments.cloud'),
        'api_key'  => env('REFLOWPAY_API_KEY'),
        'timeout'  => env('REFLOWPAY_TIMEOUT', 15),
    ],

    // ---------------------------------------
    // REFLOWPAY (CASHOUT)
    // ---------------------------------------
    'reflowpay_cashout' => [
        'base_url'  => env('REFLOWPAY_CASHOUT_BASE_URL', 'https://cashout.safepayments.cloud'),
        'api_key'   => env('REFLOWPAY_API_KEY'),
        'timeout'   => env('REFLOWPAY_TIMEOUT', 15),
        'min_reais' => env('REFLOWPAY_MIN_REAIS', 20),
        'fixed_fee' => env('REFLOWPAY_FIXED_FEE', 10.00),
    ],

    // ---------------------------------------
    // LUMNIS (PIX CASHIN/CASHOUT)
    // ---------------------------------------
    'lumnis' => [
        'base_url' => env('LUMNIS_BASE_URL', 'https://api.lumnisolucoes.com.br'),
        'code'     => env('LUMNIS_CODE'),
        'token'    => env('LUMNIS_TOKEN'),
        'timeout'  => env('LUMNIS_TIMEOUT', 15),
    ],

    // ---------------------------------------
    // PODPAY (CASHOUT / PIX)
    // ---------------------------------------
    'podpay' => [
        'url'          => env('PODPAY_URL', 'https://api.podpay.co/v1'),
        'withdraw_key' => env('PODPAY_WITHDRAW_KEY'),
        'secret_key'   => env('PODPAY_SECRET_KEY'),
        'public_key'   => env('PODPAY_PUBLIC_KEY'),
        'timeout'      => env('PODPAY_TIMEOUT', 15),
    ],

    // ---------------------------------------
    // 🦈 SHARKBANK (PIX CASHIN / CASHOUT)
    // ---------------------------------------
    'sharkbank' => [
        'url'         => env('SHARKBANK_URL', 'https://api.sharkbanking.com.br'),
        'public_key'  => env('SHARKBANK_PUBLIC_KEY'),
        'secret_key'  => env('SHARKBANK_SECRET_KEY'),
        'timeout'     => env('SHARKBANK_TIMEOUT', 15),
    ],

];
