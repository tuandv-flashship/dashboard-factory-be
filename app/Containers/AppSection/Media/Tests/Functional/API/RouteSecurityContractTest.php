<?php

namespace App\Containers\AppSection\Media\Tests\Functional\API;

use App\Containers\AppSection\Media\Tests\Functional\ApiTestCase;
use Illuminate\Routing\Route;

final class RouteSecurityContractTest extends ApiTestCase
{
    public function testMediaRoutesKeepSecurityMiddlewareContract(): void
    {
        $this->assertRouteHasMiddleware('api_media_list_media', [
            'auth:api',
        ]);

        $this->assertRouteHasMiddleware('api_media_list_media_folder_tree', [
            'auth:api',
        ]);

        $this->assertRouteHasMiddleware('api_media_list_media_folder_list', [
            'auth:api',
        ]);

        $this->assertRouteHasMiddleware('api_media_create_media_folder', [
            'auth:api',
        ]);

        $this->assertRouteHasMiddleware('api_media_upload_media_file', [
            'auth:api',
        ]);

        $this->assertRouteHasMiddleware('api_media_download_media_file', [
            'auth:api',
        ]);

        $this->assertRouteHasMiddleware('api_media_global_action', [
            'auth:api',
        ]);

        $this->assertRouteHasMiddleware('api_media_get_options', [
            'auth:api',
        ]);

        $this->assertRouteHasMiddleware('media.indirect.url', [
            'throttle:' . config('media.throttle.show_file', '120,1'),
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

