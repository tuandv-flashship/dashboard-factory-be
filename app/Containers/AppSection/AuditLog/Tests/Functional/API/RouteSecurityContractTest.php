<?php

namespace App\Containers\AppSection\AuditLog\Tests\Functional\API;

use App\Containers\AppSection\AuditLog\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\AuditLog\UI\API\Controllers\DeleteAllAuditLogsController;
use App\Containers\AppSection\AuditLog\UI\API\Controllers\DeleteAuditLogController;
use App\Containers\AppSection\AuditLog\UI\API\Controllers\GetAuditLogWidgetController;
use App\Containers\AppSection\AuditLog\UI\API\Controllers\ListAuditLogsController;
use Illuminate\Routing\Route;

final class RouteSecurityContractTest extends ApiTestCase
{
    public function testAuditLogRoutesKeepAuthMiddlewareContract(): void
    {
        $authOnlyControllers = [
            DeleteAllAuditLogsController::class,
            DeleteAuditLogController::class,
            GetAuditLogWidgetController::class,
            ListAuditLogsController::class,
        ];

        foreach ($authOnlyControllers as $controller) {
            $this->assertControllerRouteHasMiddleware($controller, ['auth:api']);
        }
    }

    /**
     * @param array<int, string> $expectedMiddlewares
     */
    private function assertControllerRouteHasMiddleware(string $controller, array $expectedMiddlewares): void
    {
        $route = $this->findRouteByController($controller);
        $middlewares = $route->gatherMiddleware();

        foreach ($expectedMiddlewares as $expectedMiddleware) {
            $this->assertContains(
                $expectedMiddleware,
                $middlewares,
                sprintf('Route for "%s" must include middleware "%s".', $controller, $expectedMiddleware),
            );
        }
    }

    private function findRouteByController(string $controller): Route
    {
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            $action = $route->getActionName();
            if ($action === $controller || $action === $controller . '@__invoke') {
                return $route;
            }
        }

        $this->fail(sprintf('Route for controller "%s" is not registered.', $controller));
    }
}
