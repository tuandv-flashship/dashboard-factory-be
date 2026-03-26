<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Shift\Actions\CopyShiftAction;
use App\Containers\AppSection\Shift\UI\API\Requests\CopyShiftRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CopyShiftController extends ApiController
{
    public function __invoke(CopyShiftRequest $request): JsonResponse
    {
        $result = app(CopyShiftAction::class)->run(
            $request->input('shift_ids'),
            $request->input('target_dates'),
        );

        return response()->json([
            'data' => [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
            ],
            'message' => sprintf(
                'Đã sao chép %d ca, bỏ qua %d ngày.',
                count($result['created']),
                count($result['skipped']),
            ),
        ], count($result['created']) > 0 ? 201 : 200);
    }
}
