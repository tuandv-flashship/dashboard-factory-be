<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Models\ShiftDetailChange;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftDetailChangeTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListShiftDetailChangesController extends ApiController
{
    public function __invoke(int $shiftDetailId): JsonResponse
    {
        $changes = ShiftDetailChange::query()
            ->where('shift_detail_id', $shiftDetailId)
            ->latest('created_at')
            ->paginate(50);

        return Response::create($changes, ShiftDetailChangeTransformer::class)->ok();
    }
}
