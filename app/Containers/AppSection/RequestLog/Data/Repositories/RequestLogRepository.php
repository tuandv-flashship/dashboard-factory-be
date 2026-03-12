<?php

namespace App\Containers\AppSection\RequestLog\Data\Repositories;

use App\Containers\AppSection\RequestLog\Models\RequestLog;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of RequestLog
 *
 * @extends ParentRepository<TModel>
 */
final class RequestLogRepository extends ParentRepository
{
    protected int $maxPaginationLimit = 200;

    protected $fieldSearchable = [
        'id' => '=',
        'url' => 'like',
        'status_code' => '=',
    ];

    public function model(): string
    {
        return RequestLog::class;
    }
}
