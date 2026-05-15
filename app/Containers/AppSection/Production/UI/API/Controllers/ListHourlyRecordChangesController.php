<?php

namespace App\Containers\AppSection\Production\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Production\Models\HourlyRecordChange;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordChangeTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListHourlyRecordChangesController extends ApiController
{
    public function __invoke(int $hourlyRecordId): JsonResponse
    {
        $changes = HourlyRecordChange::query()
            ->where('hourly_record_id', $hourlyRecordId)
            ->latest('created_at')
            ->paginate(50);

        return Response::create($changes, HourlyRecordChangeTransformer::class)->ok();
    }
}
