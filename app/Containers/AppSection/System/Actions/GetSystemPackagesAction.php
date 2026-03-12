<?php

namespace App\Containers\AppSection\System\Actions;

use App\Containers\AppSection\System\Tasks\GetSystemPackagesTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Pagination\LengthAwarePaginator;

final class GetSystemPackagesAction extends ParentAction
{
    public function __construct(private readonly GetSystemPackagesTask $getSystemPackagesTask)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $query
     */
    public function run(array $filters = [], array $query = []): LengthAwarePaginator
    {
        return $this->getSystemPackagesTask->run($filters, $query);
    }
}
