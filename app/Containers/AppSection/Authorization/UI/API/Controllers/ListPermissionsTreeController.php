<?php

namespace App\Containers\AppSection\Authorization\UI\API\Controllers;

use App\Containers\AppSection\Authorization\Actions\ListPermissionsTreeAction;
use App\Containers\AppSection\Authorization\UI\API\Requests\ListPermissionsTreeRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListPermissionsTreeController extends ApiController
{
    public function __invoke(
        ListPermissionsTreeRequest $request,
        ListPermissionsTreeAction $action,
    ): JsonResponse {
        $permissions = $action->run($request->input('guard'));

        return response()->json([
            'data' => $permissions,
        ]);
    }
}
