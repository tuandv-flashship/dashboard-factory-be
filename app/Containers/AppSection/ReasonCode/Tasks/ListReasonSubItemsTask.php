<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Data\Repositories\ReasonSubItemRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListReasonSubItemsTask extends ParentTask
{
    public function __construct(
        private readonly ReasonSubItemRepository $repository,
    ) {}

    public function run(): LengthAwarePaginator
    {
        return $this->repository
            ->addRequestCriteria()
            ->orderBy('sort_order')
            ->paginate();
    }
}
