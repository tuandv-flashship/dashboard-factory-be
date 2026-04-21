<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Traits\InvalidatesHourlyIssueCache;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ResolveHourlyIssueTask extends ParentTask
{
    use InvalidatesHourlyIssueCache;

    /**
     * Mark an issue as resolved (set resolved_at = now).
     * Idempotent: re-resolving updates the timestamp and resolution note.
     */
    public function run(int $issueId, ?string $resolution = null): HourlyIssue
    {
        $issue = HourlyIssue::findOrFail($issueId);

        $issue->update([
            'resolved_at' => now(),
            'resolution'  => $resolution,
        ]);

        $this->invalidateCacheForIssue($issue);

        return $issue->fresh();
    }
}
