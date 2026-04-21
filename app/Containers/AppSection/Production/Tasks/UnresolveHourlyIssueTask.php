<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Traits\InvalidatesHourlyIssueCache;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class UnresolveHourlyIssueTask extends ParentTask
{
    use InvalidatesHourlyIssueCache;

    /**
     * Undo resolution: clear resolved_at and resolution note.
     */
    public function run(int $issueId): HourlyIssue
    {
        $issue = HourlyIssue::findOrFail($issueId);

        $issue->update([
            'resolved_at' => null,
            'resolution'  => null,
        ]);

        $this->invalidateCacheForIssue($issue);

        return $issue->fresh();
    }
}
