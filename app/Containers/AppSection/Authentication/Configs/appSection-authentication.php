<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Clients
    |--------------------------------------------------------------------------
    |
    | A list of clients that have access to the application.
    |
    */
    'clients' => [
        'web' => [
            'id' => env('CLIENT_WEB_ID'),
            'secret' => env('CLIENT_WEB_SECRET'),
        ],
        'member' => [
            'id' => env('CLIENT_MEMBER_ID'),
            'secret' => env('CLIENT_MEMBER_SECRET'),
        ],
        'mobile' => [
            'id' => env('CLIENT_MOBILE_ID'),
            'secret' => env('CLIENT_MOBILE_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Token Expiration Time
    |--------------------------------------------------------------------------
    |
    | In Minutes. Default to 1,440 minutes = 1 day
    |
    */
    'tokens-expire-in' => env('API_TOKEN_EXPIRES', 1440),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token Expiration Time
    |--------------------------------------------------------------------------
    |
    | In Minutes. Default to 43,200 minutes = 30 days
    |
    */
    'refresh-tokens-expire-in' => env('API_REFRESH_TOKEN_EXPIRES', 43200),

    /*
    |--------------------------------------------------------------------------
    | API Throttle
    |--------------------------------------------------------------------------
    |
    | Rate limits for public Authentication endpoints.
    |
    */
    'throttle' => [
        'welcome' => env('AUTH_WELCOME_THROTTLE', '120,1'),
        'register' => env('AUTH_REGISTER_THROTTLE', '6,1'),
        'web_login' => env('AUTH_WEB_LOGIN_THROTTLE', '10,1'),
        'web_refresh' => env('AUTH_WEB_REFRESH_THROTTLE', '20,1'),
        'forgot_password' => env('AUTH_FORGOT_PASSWORD_THROTTLE', '6,1'),
        'reset_password' => env('AUTH_RESET_PASSWORD_THROTTLE', '6,1'),
        'send_verification' => env('AUTH_SEND_VERIFICATION_THROTTLE', '6,1'),
        'verify_email' => env('AUTH_VERIFY_EMAIL_THROTTLE', '20,1'),
    ],
];
