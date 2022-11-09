<?php

// config for Uzbek/Paynet
return [
    'base_url' => env('PAYNET_BASE_URL', 'https://api.paynet.uz/v2/'),
    'username' => env('PAYNET_USERNAME', 'demo'),
    'password' => env('PAYNET_PASSWORD', 'demopassword'),
    'terminal_id' => env('PAYNET_TERMINAL_ID', 'demo'),
    'token_life_time' => env('PAYNET_TOKEN_LIFE_TIME', 0),

    'proxy_url' => env('PAYNET_PROXY_URL', ''),
    'proxy_proto' => env('PAYNET_PROXY_PROTO', ''),
    'proxy_host' => env('PAYNET_PROXY_HOST', ''),
    'proxy_port' => env('PAYNET_PROXY_PORT', ''),
];
