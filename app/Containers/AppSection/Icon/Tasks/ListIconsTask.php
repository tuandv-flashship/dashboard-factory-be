<?php

namespace App\Containers\AppSection\Icon\Tasks;

use App\Containers\AppSection\Icon\Supports\IconManager;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ListIconsTask extends ParentTask
{
    public function __construct(
        private readonly IconManager $iconManager,
    ) {
    }

    /**
     * @return array{data: array, meta: array}
     */
    public function run(?string $search = null, int $page = 1, int $perPage = 100): array
    {
        $icons = $this->iconManager->search($search);

        $result = $this->iconManager->paginate($icons, $page, $perPage);

        $result['meta']['prefix'] = $this->iconManager->prefix();

        if ($search !== null && $search !== '') {
            $result['meta']['search'] = $search;
        }

        return $result;
    }
}
