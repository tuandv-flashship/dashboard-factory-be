<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Traits\InvalidatesHourlyIssueCache;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Validation\ValidationException;

final class UpdateHourlyIssueTask extends ParentTask
{
    use InvalidatesHourlyIssueCache;

    public function run(int $issueId, array $data): HourlyIssue
    {
        $issue = HourlyIssue::findOrFail($issueId);

        // Only update fields actually present in the validated payload.
        // 'sometimes' validation ensures only sent fields are in $data.
        $payload = collect($data)->only([
            'category', 'sub_item', 'error', 'note', 'productivity_item_id',
        ])->toArray();

        // Validate productivity_item_id against the parent record's productivity_json
        if (array_key_exists('productivity_item_id', $payload) && $payload['productivity_item_id'] !== null) {
            $record = $issue->hourlyRecord ?? HourlyRecord::find($issue->hourly_record_id);
            $items  = $record?->productivity_json ?? [];
            $exists = collect($items)->contains('_id', $payload['productivity_item_id']);

            if (!$exists) {
                throw ValidationException::withMessages([
                    'productivity_item_id' => "Item [{$payload['productivity_item_id']}] not found in this hourly record.",
                ]);
            }
        }

        // Handle resolved toggle when explicitly sent
        if (array_key_exists('resolved', $data)) {
            $resolving = (bool) $data['resolved'];

            $payload['resolved_at'] = $resolving
                ? ($issue->resolved_at ?? now())   // preserve existing timestamp if already resolved
                : null;

            $payload['resolution'] = $resolving
                ? ($data['resolution'] ?? $issue->resolution) // keep existing if not re-sent
                : null;
        } elseif (array_key_exists('resolution', $data)) {
            // Allow updating resolution text without toggling resolved state
            $payload['resolution'] = $data['resolution'];
        }

        $issue->update($payload);

        $this->invalidateCacheForIssue($issue);

        return $issue->fresh();
    }
}

