<?php

namespace App\Containers\AppSection\Table\UI\API\Controllers;

use App\Containers\AppSection\Table\Actions\GetFormMetaAction;
use App\Containers\AppSection\Table\UI\API\Requests\FormMetaRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class FormMetaController extends ApiController
{
    public function __invoke(FormMetaRequest $request, GetFormMetaAction $action): JsonResponse
    {
        $result = $action->run(
            $request->query('model'),
            $request->query('action', 'create'),
            $request->user(),
        );

        return response()->json(['data' => $result]);
    }
}
