<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Data\Repositories\ShiftTemplateRepository;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListShiftTemplatesTask extends ParentTask
{
    public function __construct(
        private readonly ShiftTemplateRepository $repository,
    ) {}

    public function run(): LengthAwarePaginator
    {
        return $this->repository
            ->addRequestCriteria()
            ->with('details.department')
            ->orderBy('sort_order')
            ->paginate();
    }
}
