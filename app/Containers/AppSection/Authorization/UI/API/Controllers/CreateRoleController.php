<?php

namespace App\Containers\AppSection\Authorization\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Authorization\Actions\CreateRoleAction;
use App\Containers\AppSection\Authorization\UI\API\Requests\CreateRoleRequest;
use App\Containers\AppSection\Authorization\UI\API\Transformers\RoleAdminTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateRoleController extends ApiController
{
    public function __invoke(CreateRoleRequest $request, CreateRoleAction $action): JsonResponse
    {
        $payload = $request->validated();

        $role = $action->run(
            $payload['name'],
            $payload['description'] ?? null,
            $payload['display_name'] ?? null,
            $payload['permission_ids'] ?? null,
        );

        return Response::create($role, RoleAdminTransformer::class)->created();
    }
}
