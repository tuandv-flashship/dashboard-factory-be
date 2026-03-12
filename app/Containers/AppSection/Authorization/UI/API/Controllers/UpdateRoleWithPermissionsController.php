<?php

namespace App\Containers\AppSection\Authorization\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Authorization\Actions\UpdateRoleWithPermissionsAction;
use App\Containers\AppSection\Authorization\UI\API\Requests\UpdateRoleWithPermissionsRequest;
use App\Containers\AppSection\Authorization\UI\API\Transformers\RoleAdminTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Support\Arr;

final class UpdateRoleWithPermissionsController extends ApiController
{
    public function __invoke(
        UpdateRoleWithPermissionsRequest $request,
        UpdateRoleWithPermissionsAction $action
    ): array {
        $payload = $request->validated();
        $data = Arr::only($payload, ['display_name', 'description']);

        $role = $action->run($request->role_id, $data, $payload['permission_ids'] ?? null);

        return Response::create($role, RoleAdminTransformer::class)->parseIncludes(['permissions'])->toArray();
    }
}
