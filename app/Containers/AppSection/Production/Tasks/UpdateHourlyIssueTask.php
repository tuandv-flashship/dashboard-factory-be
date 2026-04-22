<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Traits\InvalidatesHourlyIssueCache;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class UpdateHourlyIssueTask extends ParentTask
{
    use InvalidatesHourlyIssueCache;

    public function run(int $issueId, array $data): HourlyIssue
    {
        $issue = HourlyIssue::findOrFail($issueId);

        // Only update fields actually present in the validated payload.
        // 'sometimes' validation ensures only sent fields are in $data.
        $payload = collect($data)->only(['category', 'sub_item', 'error', 'note'])->toArray();

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

