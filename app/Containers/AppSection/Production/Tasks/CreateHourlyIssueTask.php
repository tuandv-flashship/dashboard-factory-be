<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Traits\InvalidatesHourlyIssueCache;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class CreateHourlyIssueTask extends ParentTask
{
    use InvalidatesHourlyIssueCache;

    public function run(int $hourlyRecordId, array $data): HourlyIssue
    {
        $record = HourlyRecord::findOrFail($hourlyRecordId);

        $issue = HourlyIssue::create([
            'hourly_record_id' => $record->id,
            'category'         => $data['category'],
            'sub_item'         => $data['sub_item'],
            'error'            => $data['error'],
            'note'             => $data['note'] ?? null,
        ]);

        $this->invalidateCacheForIssue($issue);

        return $issue;
    }
}
