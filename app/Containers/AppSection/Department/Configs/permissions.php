<?php

return [
    ['name' => 'Cài đặt bộ phận', 'flag' => 'departments.index',   'parent_flag' => 'group.operation-admin', 'order' => 2],
    ['name' => 'Thêm',            'flag' => 'departments.create',  'parent_flag' => 'departments.index',     'order' => 1],
    ['name' => 'Sửa',             'flag' => 'departments.edit',    'parent_flag' => 'departments.index',     'order' => 2],
    ['name' => 'Xóa',             'flag' => 'departments.destroy', 'parent_flag' => 'departments.index',     'order' => 3],
];
