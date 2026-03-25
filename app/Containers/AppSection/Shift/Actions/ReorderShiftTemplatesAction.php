<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\UI\API\Requests\ReorderShiftTemplatesRequest;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class ReorderShiftTemplatesAction extends ParentAction
{
    public function run(ReorderShiftTemplatesRequest $request): void
    {
        DB::transaction(function () use ($request) {
            foreach ($request->items as $item) {
                ShiftTemplate::where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }
}
