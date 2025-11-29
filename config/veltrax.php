<?php

return [
    'base_url'      => env('VELTRAX_BASE_URL', 'https://api.veltraxpay.com'),
    'client_id'     => env('VELTRAX_CLIENT_ID'),
    'client_secret' => env('VELTRAX_CLIENT_SECRET'),
    'callback_url'  => env('VELTRAX_CALLBACK_URL'),
    // minutos para cache do token
    'token_ttl'     => 55,
];
