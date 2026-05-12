<?php

return [
    ['name' => 'Cài đặt mức đánh giá KPI', 'flag' => 'kpi-rating-levels.index',   'parent_flag' => 'group.operation-admin',    'order' => 3],
    ['name' => 'Thêm',                      'flag' => 'kpi-rating-levels.create',  'parent_flag' => 'kpi-rating-levels.index',  'order' => 1],
    ['name' => 'Sửa',                       'flag' => 'kpi-rating-levels.edit',    'parent_flag' => 'kpi-rating-levels.index',  'order' => 2],
    ['name' => 'Xóa',                       'flag' => 'kpi-rating-levels.destroy', 'parent_flag' => 'kpi-rating-levels.index',  'order' => 3],
];
