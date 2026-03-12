<?php

namespace App\Containers\AppSection\Authentication\Tests\Unit\Configs;

use App\Containers\AppSection\Authentication\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class AuthenticationTest extends UnitTestCase
{
    public function testConfigHasCorrectValues(): void
    {
        $config = config('appSection-authentication');
        $expected = [
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
            'tokens-expire-in' => env('API_TOKEN_EXPIRES', 1440),
            'refresh-tokens-expire-in' => env('API_REFRESH_TOKEN_EXPIRES', 43200),
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

        $this->assertEquals($expected, $config);
    }
}
