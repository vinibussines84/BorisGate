<?php
// config/gateway.php

return [
    // Base oficial (sem / no final)
    'base_url'       => rtrim(env('GATEWAY_BASE_URL', 'https://api.veltraxpay.com'), '/'),

    // Credenciais
    'client_id'      => env('GATEWAY_CLIENT_ID', ''),
    'client_secret'  => env('GATEWAY_CLIENT_SECRET', ''),

    // Prefixo de API — a Veltrax usa /api
    'api_prefix'     => env('GATEWAY_API_PREFIX', '/api'),

    // Endpoints (relativos ao prefixo)
    'paths' => [
        'auth_login'  => env('GATEWAY_AUTH_LOGIN_PATH', 'auth/login'),
        // ajuste se o seu endpoint de criação for outro
        'deposit'     => env('GATEWAY_DEPOSIT_PATH', 'pix/deposit'),
    ],

    // Callback para webhooks (não retornar ao cliente)
    'callback_url'   => env('GATEWAY_CALLBACK_URL', ''),

    // Timeout HTTP
    'timeout'        => (int) env('GATEWAY_TIMEOUT', 20),

    // TTL do token em minutos (ex.: 55 p/ token de 60m)
    'token_ttl'      => (int) env('GATEWAY_TOKEN_TTL', 55),
];
