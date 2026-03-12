<?php

namespace App\Containers\AppSection\Authorization\Tests\Functional\API;

use App\Containers\AppSection\Authorization\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\Authorization\UI\API\Controllers\AssignRolesToUserController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\CreateRoleController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\DeleteRoleController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\FindPermissionByIdController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\FindRoleByIdController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\GivePermissionsToRoleController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\GivePermissionsToUserController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\ListPermissionsController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\ListPermissionsTreeController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\ListRolePermissionsController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\ListRolesController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\ListUserPermissionsController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\ListUserRolesController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\RemoveUserRolesController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\RevokeRolePermissionsController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\RevokeUserPermissionsController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\SyncRolePermissionsController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\SyncUserRolesController;
use App\Containers\AppSection\Authorization\UI\API\Controllers\UpdateRoleWithPermissionsController;
use Illuminate\Routing\Route;

final class RouteSecurityContractTest extends ApiTestCase
{
    public function testAuthorizationRoutesKeepAuthMiddlewareContract(): void
    {
        $authOnlyControllers = [
            AssignRolesToUserController::class,
            CreateRoleController::class,
            DeleteRoleController::class,
            FindPermissionByIdController::class,
            FindRoleByIdController::class,
            GivePermissionsToRoleController::class,
            GivePermissionsToUserController::class,
            ListPermissionsController::class,
            ListPermissionsTreeController::class,
            ListRolePermissionsController::class,
            ListRolesController::class,
            ListUserPermissionsController::class,
            ListUserRolesController::class,
            RemoveUserRolesController::class,
            RevokeRolePermissionsController::class,
            RevokeUserPermissionsController::class,
            SyncRolePermissionsController::class,
            SyncUserRolesController::class,
            UpdateRoleWithPermissionsController::class,
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
