<?php

namespace Tests\Unit\Configs;

use App\Ship\Tests\ShipTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class CorsTest extends ShipTestCase
{
    public function testConfigHasCorrectValues(): void
    {
        $config = config('cors');
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

        $expected = [
            'paths' => ['*', 'sanctum/csrf-cookie'],
            'allowed_methods' => ['*'],
            'allowed_origins' => $allowedOrigins,
            'allowed_origins_patterns' => [],
            'allowed_headers' => ['*'],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => false,
        ];

        $this->assertEqualsCanonicalizing($expected, $config);
    }
}
