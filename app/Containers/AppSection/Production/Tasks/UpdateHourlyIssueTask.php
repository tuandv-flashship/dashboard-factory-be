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
        // collect()->only() safely handles partial payloads including note=null.
        $issue->update(
            collect($data)->only(['category', 'sub_item', 'error', 'note'])->toArray()
        );

        $this->invalidateCacheForIssue($issue);

        return $issue->fresh();
    }
}
