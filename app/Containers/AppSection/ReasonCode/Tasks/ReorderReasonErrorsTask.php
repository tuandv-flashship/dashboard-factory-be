<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ReorderReasonErrorsTask extends ParentTask
{
    /**
     * Bulk-update sort_order in a single upsert query (eliminates N+1).
     *
     * @param array<int, array{id: int, sort_order: int}> $items
     */
    public function run(array $items): void
    {
        ReasonError::upsert(
            array_map(fn($i) => ['id' => $i['id'], 'sort_order' => $i['sort_order']], $items),
            uniqueBy: ['id'],
            update: ['sort_order'],
        );
    }
}
