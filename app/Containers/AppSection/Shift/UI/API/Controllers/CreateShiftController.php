<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Shift\Actions\CreateShiftAction;
use App\Containers\AppSection\Shift\UI\API\Requests\CreateShiftRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class CreateShiftController extends ApiController
{
    public function __invoke(CreateShiftRequest $request): JsonResponse
    {
        $data = $request->validated();
        $date = $data['date'];
        $action = app(CreateShiftAction::class);

        // ── Single date (string) → synchronous create (backward compat) ──
        if (is_string($date)) {
            if ($date < today()->toDateString()) {
                return response()->json([
                    'message' => 'Cannot create shift for a past date.',
                ], 422);
            }

            $shift = $action->run($data);

            return Response::create($shift, ShiftTransformer::class)->created();
        }

        // ── Multi-date (array) → parallel batch jobs ──
        $shiftData = collect($data)->except('date')->toArray();
        $result    = $action->runMultiDate($date, $shiftData);

        if ($result['total'] === 0) {
            return response()->json([
                'message'      => 'All dates are in the past. No shifts created.',
                'past_ignored' => $result['past_ignored'],
            ], 422);
        }

        return response()->json([
            'message'      => "Dispatched {$result['total']} shift creation job(s).",
            'batch_id'     => $result['batch_id'],
            'total'        => $result['total'],
            'past_ignored' => $result['past_ignored'],
        ], 202);
    }
}
