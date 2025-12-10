<?php

return [
    'token' => env('XFLOW_TOKEN'),
    'base_url' => env('XFLOW_BASE_URL', 'https://api.xflowpayments.co'),
    'timeout' => env('XFLOW_TIMEOUT', 15),
];
