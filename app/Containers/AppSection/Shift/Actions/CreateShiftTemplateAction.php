<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Tasks\CreateShiftTemplateTask;
use App\Containers\AppSection\Shift\Tasks\SyncShiftTemplateDetailsTask;
use App\Containers\AppSection\Shift\UI\API\Requests\CreateShiftTemplateRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class CreateShiftTemplateAction extends ParentAction
{
    public function run(CreateShiftTemplateRequest $request): ShiftTemplate
    {
        return DB::transaction(function () use ($request) {
            $template = app(CreateShiftTemplateTask::class)->run([
                'name'                => $request->name,
                'color'               => $request->color ?? '#0000FF',
                'description'         => $request->description,
                'sort_order'          => $request->sort_order ?? 0,
                'status'              => $request->status ?? 'active',
                'applies_to_shift_1'  => $request->applies_to_shift_1 ?? true,
                'applies_to_shift_2'  => $request->applies_to_shift_2 ?? false,
            ]);

            if ($request->has('details')) {
                app(SyncShiftTemplateDetailsTask::class)->run(
                    $template->id,
                    $request->details,
                );
            }

            return $template->load('details.department');
        });
    }
}
