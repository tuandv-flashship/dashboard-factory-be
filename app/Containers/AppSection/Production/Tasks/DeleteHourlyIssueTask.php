<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Traits\InvalidatesHourlyIssueCache;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class DeleteHourlyIssueTask extends ParentTask
{
    use InvalidatesHourlyIssueCache;

    public function run(int $issueId): void
    {
        $issue = HourlyIssue::findOrFail($issueId);

        // Invalidate before deletion so we still have relation data
        $this->invalidateCacheForIssue($issue);

        $issue->delete();
    }
}
