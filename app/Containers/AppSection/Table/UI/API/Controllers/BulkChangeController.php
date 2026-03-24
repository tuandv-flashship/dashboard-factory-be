<?php

namespace App\Containers\AppSection\Table\UI\API\Controllers;

use App\Containers\AppSection\Table\Actions\DispatchBulkChangeAction;
use App\Containers\AppSection\Table\UI\API\Requests\BulkChangeRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class BulkChangeController extends ApiController
{
    public function __invoke(BulkChangeRequest $request, DispatchBulkChangeAction $action): JsonResponse
    {
        $result = $action->run(
            $request->input('model'),
            $request->input('ids'),
            $request->input('key'),
            $request->input('value'),
        );

        return response()->json([
            'message' => trans('table::actions.bulk_change_completed'),
            'data' => [
                'success' => $result['success'],
                'failed' => $result['failed'],
            ],
            'errors' => $result['errors'],
        ]);
    }
}
