<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\CreateDailyShiftAction;
use App\Containers\AppSection\Shift\UI\API\Requests\CreateDailyShiftRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateDailyShiftController extends ApiController
{
    public function __invoke(CreateDailyShiftRequest $request): JsonResponse
    {
        $result = app(CreateDailyShiftAction::class)->run($request->date);

        if ($result['status'] === 'created') {
            return Response::create($result['shift'], ShiftTransformer::class)->created();
        }

        if ($result['status'] === 'inventory_updated') {
            return Response::create($result['shift'], ShiftTransformer::class)->ok();
        }

        return response()->json([
            'status'  => $result['status'],
            'message' => $result['message'],
        ], 200);
    }
}
