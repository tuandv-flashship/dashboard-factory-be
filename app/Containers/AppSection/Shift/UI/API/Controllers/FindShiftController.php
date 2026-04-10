<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\FplatformData\Tasks\GetAllTeamsInventoryTask;
use App\Containers\AppSection\Shift\Actions\FindShiftWithDetailsAction;
use App\Containers\AppSection\Shift\UI\API\Requests\FindShiftRequest;
use App\Containers\AppSection\Shift\UI\API\Transformers\ShiftTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class FindShiftController extends ApiController
{
    public function __invoke(FindShiftRequest $request): JsonResponse
    {
        $wantsInventory = $this->wantsInventory($request);

        // Strip 'inventory' from include param so Fractal doesn't try to resolve it
        if ($wantsInventory) {
            $this->stripInventoryFromInclude($request);
        }

        $shift = app(FindShiftWithDetailsAction::class)->run((int) $request->id);

        $response = Response::create($shift, ShiftTransformer::class);

        // Add inventory data to meta when include=inventory is requested
        if ($wantsInventory) {
            $shiftDate = $shift->date->toDateString();
            $today = now()->toDateString();

            // Future dates have no inventory data — skip query
            $inventory = $shiftDate <= $today
                ? app(GetAllTeamsInventoryTask::class)->run($shiftDate)
                : null;

            $response->addMeta([
                'include'   => array_merge(
                    (new ShiftTransformer())->getAvailableIncludes(),
                    ['inventory'],
                ),
                'inventory' => $inventory,
            ]);
        }

        return $response->ok();
    }

    /**
     * Check if the request includes 'inventory' in the include parameter.
     */
    private function wantsInventory(FindShiftRequest $request): bool
    {
        $includes = $request->input('include', '');

        return in_array('inventory', array_map('trim', explode(',', $includes)), true);
    }

    /**
     * Remove 'inventory' from the include query parameter so Fractal
     * doesn't attempt to call a non-existent includeInventory() method.
     */
    private function stripInventoryFromInclude(FindShiftRequest $request): void
    {
        $includes = array_map('trim', explode(',', $request->input('include', '')));
        $filtered = array_filter($includes, fn (string $inc) => $inc !== 'inventory');

        $request->merge(['include' => implode(',', $filtered)]);
    }
}
