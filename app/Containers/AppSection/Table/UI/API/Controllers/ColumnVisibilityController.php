<?php

namespace App\Containers\AppSection\Table\UI\API\Controllers;

use App\Containers\AppSection\Table\Actions\SaveColumnVisibilityAction;
use App\Containers\AppSection\Table\UI\API\Requests\ColumnVisibilityRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ColumnVisibilityController extends ApiController
{
    public function __invoke(ColumnVisibilityRequest $request, SaveColumnVisibilityAction $action): JsonResponse
    {
        $action->run(
            $request->user(),
            $request->input('model'),
            $request->input('columns'),
        );

        return response()->json(['message' => 'Column preferences saved.']);
    }
}
