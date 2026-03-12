<?php

namespace App\Containers\AppSection\User\Tests\Functional\API;

use App\Containers\AppSection\User\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\User\UI\API\Controllers\AdminBootstrapController;
use App\Containers\AppSection\User\UI\API\Controllers\CreateUserController;
use App\Containers\AppSection\User\UI\API\Controllers\DeleteUserController;
use App\Containers\AppSection\User\UI\API\Controllers\FindUserByIdController;
use App\Containers\AppSection\User\UI\API\Controllers\GetUserProfileController;
use App\Containers\AppSection\User\UI\API\Controllers\ListUsersController;
use App\Containers\AppSection\User\UI\API\Controllers\UpdatePasswordController;
use App\Containers\AppSection\User\UI\API\Controllers\UpdateUserAvatarController;
use App\Containers\AppSection\User\UI\API\Controllers\UpdateUserController;
use Illuminate\Routing\Route;

final class RouteSecurityContractTest extends ApiTestCase
{
    public function testUserRoutesKeepAuthMiddlewareContract(): void
    {
        $authOnlyControllers = [
            AdminBootstrapController::class,
            CreateUserController::class,
            DeleteUserController::class,
            FindUserByIdController::class,
            GetUserProfileController::class,
            ListUsersController::class,
            UpdatePasswordController::class,
            UpdateUserAvatarController::class,
            UpdateUserController::class,
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
