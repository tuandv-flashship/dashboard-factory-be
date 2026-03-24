<?php

namespace App\Containers\AppSection\Table\UI\API\Controllers;

use App\Containers\AppSection\Table\Actions\DispatchBulkActionAction;
use App\Containers\AppSection\Table\UI\API\Requests\BulkActionRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class BulkActionController extends ApiController
{
    public function __invoke(BulkActionRequest $request, DispatchBulkActionAction $action): JsonResponse
    {
        $result = $action->run(
            $request->input('model'),
            $request->input('action'),
            $request->input('ids'),
        );

        return response()->json([
            'message' => trans('table::actions.bulk_action_completed'),
            'data' => [
                'success' => $result['success'],
                'failed' => $result['failed'],
            ],
            'errors' => $result['errors'],
        ]);
    }
}
