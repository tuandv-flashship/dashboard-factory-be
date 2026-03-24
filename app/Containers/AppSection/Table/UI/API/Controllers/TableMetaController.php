<?php

namespace App\Containers\AppSection\Table\UI\API\Controllers;

use App\Containers\AppSection\Table\Actions\GetTableMetaAction;
use App\Containers\AppSection\Table\UI\API\Requests\TableMetaRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class TableMetaController extends ApiController
{
    public function __invoke(TableMetaRequest $request, GetTableMetaAction $action): JsonResponse
    {
        $result = $action->run(
            $request->query('model'),
            $request->user(),
        );

        return response()->json(['data' => $result]);
    }
}
