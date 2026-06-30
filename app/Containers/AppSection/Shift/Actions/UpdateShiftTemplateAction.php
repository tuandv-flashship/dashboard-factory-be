<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Containers\AppSection\Shift\Tasks\FindShiftTemplateByIdTask;
use App\Containers\AppSection\Shift\Tasks\PropagateShiftTemplateChangesTask;
use App\Containers\AppSection\Shift\Tasks\SyncShiftTemplateDetailsTask;
use App\Containers\AppSection\Shift\Tasks\UpdateShiftTemplateTask;
use App\Containers\AppSection\Shift\UI\API\Requests\UpdateShiftTemplateRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class UpdateShiftTemplateAction extends ParentAction
{
    public function run(UpdateShiftTemplateRequest $request): ShiftTemplate
    {
        $shiftsToResync = [];

        $template = DB::transaction(function () use ($request, &$shiftsToResync) {
            $template = app(FindShiftTemplateByIdTask::class)->run($request->id);

            $fields = ['name', 'color', 'description', 'sort_order',
                'status', 'applies_to_shift_1', 'applies_to_shift_2',
            ];

            // Only include fields that were actually sent in the request.
            // Do NOT filter by value — nullable fields like "description"
            // may be sent as "" (converted to null by middleware) and must
            // still be persisted.
            $data = $request->only(array_filter($fields, fn ($f) => $request->has($f)));

            app(UpdateShiftTemplateTask::class)->run($template, $data);

            if ($request->has('details')) {
                // 1. Fetch old details BEFORE syncing/deleting them to detect which departments changed
                $oldDetails = ShiftTemplateDetail::where('shift_template_id', $template->id)->get();

                app(SyncShiftTemplateDetailsTask::class)->run(
                    $template->id,
                    $request->details,
                );

                // 2. Propagate only changed departments' updates to shifts today onwards
                $shiftsToResync = app(PropagateShiftTemplateChangesTask::class)->run(
                    $template,
                    $oldDetails,
                    $request->details
                );
            }

            return $template->fresh('details.department');
        });

        // 3. Trigger FPlatform resync outside the transaction to prevent race conditions
        if (!empty($shiftsToResync)) {
            $productionSyncTask = app(\App\Containers\AppSection\Production\Tasks\SyncHourlyRecordsTask::class);
            foreach ($shiftsToResync as $shift) {
                try {
                    Log::info('[UpdateShiftTemplateAction] Dispatching FPlatform resync for today\'s updated shift', [
                        'shift_id'     => $shift->id,
                        'date'         => $shift->date->toDateString(),
                        'shift_number' => $shift->shift_number,
                    ]);

                    $productionSyncTask->run(
                        date:        $shift->date->toDateString(),
                        shiftNumber: $shift->shift_number,
                        forceAll:    true
                    );
                } catch (\Throwable $e) {
                    Log::error('[UpdateShiftTemplateAction] FPlatform resync failed during propagation', [
                        'shift_id' => $shift->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }

        return $template;
    }
}

