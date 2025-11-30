<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PodPay API Credentials
    |--------------------------------------------------------------------------
    |
    | As chaves são carregadas do arquivo .env.
    | Não coloque valores fixos aqui — sempre use env().
    |
    */

    'public_key' => env('PODPAY_PUBLIC_KEY'),
    'secret_key' => env('PODPAY_SECRET_KEY'),

];
