<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class HourlyIssueTransformer extends ParentTransformer
{
    public function transform(HourlyIssue $issue): array
    {
        return [
            'id' => $issue->getHashedKey(),
            'category' => $issue->category,
            'sub_item' => $issue->sub_item,
            'error' => $issue->error,
            'note' => $issue->note,
            'resolved_at' => $issue->resolved_at?->toIso8601String(),
            'resolution' => $issue->resolution,
        ];
    }
}
