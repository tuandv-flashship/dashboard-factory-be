<?php

$defaultOrigin = env('FRONTEND_URL', env('APP_URL', 'http://localhost:3000'));
$rawOrigins = env('CORS_ALLOWED_ORIGINS');
$allowedOrigins = [];

if (is_string($rawOrigins) && $rawOrigins !== '') {
    $allowedOrigins = array_values(array_filter(
        array_map('trim', explode(',', $rawOrigins)),
        static fn (string $origin): bool => $origin !== '',
    ));
}

if ($allowedOrigins === []) {
    $allowedOrigins = [$defaultOrigin];
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
