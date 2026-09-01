<?php

return [
    'name' => env('APP_NAME', 'GI Starter'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL'),
    'site_certificados' => env('SITE_CERTIFICADOS', 'http://localhost:8005'),
    'display_timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
];
