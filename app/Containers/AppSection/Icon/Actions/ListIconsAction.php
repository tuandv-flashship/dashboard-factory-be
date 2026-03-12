<?php

namespace App\Containers\AppSection\Icon\Actions;

use App\Containers\AppSection\Icon\Tasks\ListIconsTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ListIconsAction extends ParentAction
{
    public function __construct(
        private readonly ListIconsTask $listIconsTask,
    ) {
    }

    /**
     * @return array{data: array, meta: array}
     */
    public function run(?string $search = null, int $page = 1, int $perPage = 100): array
    {
        return $this->listIconsTask->run($search, $page, $perPage);
    }
}
