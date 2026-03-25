<?php

return [
    [
        'name'        => 'KPI Rating Levels',
        'flag'        => 'kpi-rating-levels.index',
        'parent_flag' => 'core.system',
    ],
    [
        'name'        => 'Create',
        'flag'        => 'kpi-rating-levels.create',
        'parent_flag' => 'kpi-rating-levels.index',
    ],
    [
        'name'        => 'Edit',
        'flag'        => 'kpi-rating-levels.edit',
        'parent_flag' => 'kpi-rating-levels.index',
    ],
    [
        'name'        => 'Delete',
        'flag'        => 'kpi-rating-levels.destroy',
        'parent_flag' => 'kpi-rating-levels.index',
    ],
];
