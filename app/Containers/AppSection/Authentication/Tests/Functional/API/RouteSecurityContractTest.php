<?php

namespace App\Containers\AppSection\Authentication\Tests\Functional\API;

use App\Containers\AppSection\Authentication\Tests\Functional\ApiTestCase;
use Illuminate\Routing\Route;

final class RouteSecurityContractTest extends ApiTestCase
{
    public function testAuthenticationCriticalRoutesKeepSecurityMiddlewareContract(): void
    {
        $this->assertRouteHasMiddleware('api_auth_register_user', [
            'throttle:' . config('appSection-authentication.throttle.register', '6,1'),
        ]);

        $this->assertRouteHasMiddleware('api_auth_web_issue_token', [
            'throttle:' . config('appSection-authentication.throttle.web_login', '10,1'),
        ]);

        $this->assertRouteHasMiddleware('api_auth_web_refresh_token', [
            'throttle:' . config('appSection-authentication.throttle.web_refresh', '20,1'),
        ]);

        $this->assertRouteHasMiddleware('password.email', [
            'guest',
            'throttle:' . config('appSection-authentication.throttle.forgot_password', '6,1'),
        ]);

        $this->assertRouteHasMiddleware('password.update', [
            'guest',
            'throttle:' . config('appSection-authentication.throttle.reset_password', '6,1'),
        ]);

        $this->assertRouteHasMiddleware('verification.send', [
            'auth:api',
            'throttle:' . config('appSection-authentication.throttle.send_verification', '6,1'),
        ]);

        $this->assertRouteHasMiddleware('verification.verify', [
            'auth:api',
            'signed',
            'throttle:' . config('appSection-authentication.throttle.verify_email', '20,1'),
        ]);

        $this->assertRouteHasMiddleware('api_auth_revoke_token', [
            'auth:api',
        ]);
    }

    /**
     * @param array<int, string> $expectedMiddlewares
     */
    private function assertRouteHasMiddleware(string $routeName, array $expectedMiddlewares): void
    {
        $route = app('router')->getRoutes()->getByName($routeName);
        $this->assertInstanceOf(Route::class, $route, sprintf('Route "%s" is not registered.', $routeName));

        $middlewares = $route->gatherMiddleware();

        foreach ($expectedMiddlewares as $expectedMiddleware) {
            $this->assertContains(
                $expectedMiddleware,
                $middlewares,
                sprintf('Route "%s" must include middleware "%s".', $routeName, $expectedMiddleware),
            );
        }
    }
}
