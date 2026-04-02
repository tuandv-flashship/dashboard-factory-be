<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Tasks\SyncHourlyRecordsTask;
use App\Containers\AppSection\Shift\Tasks\SyncShiftDetailsTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class UpdateShiftAction extends ParentAction
{
    public function run(int $id, array $data): Shift
    {
        return DB::transaction(function () use ($id, $data) {
            $shift = Shift::findOrFail($id);

            // Update header fields
            $headerFields = collect($data)->only([
                'supervisor', 'is_active',
            ])->toArray();

            if (!empty($headerFields)) {
                $shift->update($headerFields);
            }

            // Sync details if provided
            if (isset($data['details'])) {
                app(SyncShiftDetailsTask::class)->run($shift, $data['details']);

                // Smart sync hourly records: preserve actual data, soft-delete stale
                app(SyncHourlyRecordsTask::class)->run($shift);
            }

            return $shift->load(['details.department.productionLine', 'template', 'hourlyRecords']);
        });
    }
}
