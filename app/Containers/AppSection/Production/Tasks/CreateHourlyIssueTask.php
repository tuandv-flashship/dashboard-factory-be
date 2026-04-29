<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Traits\InvalidatesHourlyIssueCache;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Validation\ValidationException;

final class CreateHourlyIssueTask extends ParentTask
{
    use InvalidatesHourlyIssueCache;

    public function run(int $hourlyRecordId, array $data): HourlyIssue
    {
        $record = HourlyRecord::findOrFail($hourlyRecordId);

        $itemId = $data['productivity_item_id'] ?? null;

        // Validate that the item _id exists in productivity_json
        if ($itemId !== null) {
            $items  = $record->productivity_json ?? [];
            $exists = collect($items)->contains('_id', $itemId);

            if (!$exists) {
                throw ValidationException::withMessages([
                    'productivity_item_id' => "Item [{$itemId}] not found in this hourly record.",
                ]);
            }
        }

        $issue = HourlyIssue::create([
            'hourly_record_id'     => $record->id,
            'productivity_item_id' => $itemId,
            'category'             => $data['category'],
            'sub_item'             => $data['sub_item'],
            'error'                => $data['error'],
            'note'                 => $data['note'] ?? null,
            'resolved_at'          => ($data['resolved'] ?? false) ? now() : null,
            'resolution'           => ($data['resolved'] ?? false) ? ($data['resolution'] ?? null) : null,
        ]);

        $this->invalidateCacheForIssue($issue);

        return $issue;
    }
}
