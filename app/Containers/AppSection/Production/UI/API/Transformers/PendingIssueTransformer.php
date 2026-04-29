<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

/**
 * Transformer for the pending-issues side panel.
 * Includes hourly_record context (hour_slot, department) alongside issue details.
 */
final class PendingIssueTransformer extends ParentTransformer
{
    public function transform(HourlyIssue $issue): array
    {
        $record = $issue->hourlyRecord;

        return [
            'id'                    => $issue->getHashedKey(),
            'productivity_item_id'  => $issue->productivity_item_id,
            'category'              => $issue->category,
            'sub_item'              => $issue->sub_item,
            'error'                 => $issue->error,
            'note'                  => $issue->note,
            'resolved_at'           => $issue->resolved_at?->toIso8601String(),
            'resolution'            => $issue->resolution,
            // Hourly record context for the side panel
            'hour_slot'             => $record?->hour_slot,
            'hour_index'            => $record?->hour_index,
            'department_id'         => $record?->department_id,
            'department_code'       => $record?->department?->code,
        ];
    }
}
