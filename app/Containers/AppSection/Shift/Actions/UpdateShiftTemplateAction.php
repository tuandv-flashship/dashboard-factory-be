<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Tasks\FindShiftTemplateByIdTask;
use App\Containers\AppSection\Shift\Tasks\SyncShiftTemplateDetailsTask;
use App\Containers\AppSection\Shift\Tasks\UpdateShiftTemplateTask;
use App\Containers\AppSection\Shift\UI\API\Requests\UpdateShiftTemplateRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class UpdateShiftTemplateAction extends ParentAction
{
    public function run(UpdateShiftTemplateRequest $request): ShiftTemplate
    {
        return DB::transaction(function () use ($request) {
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
                app(SyncShiftTemplateDetailsTask::class)->run(
                    $template->id,
                    $request->details,
                );
            }

            return $template->fresh('details.department');
        });
    }
}
