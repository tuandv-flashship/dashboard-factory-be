<?php

namespace App\Containers\AppSection\AuditLog\Data\Repositories;

use App\Containers\AppSection\AuditLog\Models\AuditHistory;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of AuditHistory
 *
 * @extends ParentRepository<TModel>
 */
final class AuditHistoryRepository extends ParentRepository
{
    protected int $maxPaginationLimit = 200;

    protected $fieldSearchable = [
        'id' => '=',
        'module' => 'like',
        'action' => 'like',
        'reference_id' => '=',
        'reference_name' => 'like',
        'type' => '=',
    ];

    public function model(): string
    {
        return AuditHistory::class;
    }
}
