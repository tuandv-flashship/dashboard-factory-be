<?php

return [
    ['name' => 'Người dùng',             'flag' => 'users.index',   'parent_flag' => 'group.system-admin', 'order' => 1],
    ['name' => 'Thêm',                   'flag' => 'users.create',  'parent_flag' => 'users.index',        'order' => 1],
    ['name' => 'Sửa',                    'flag' => 'users.edit',    'parent_flag' => 'users.index',        'order' => 2],
    ['name' => 'Xóa',                    'flag' => 'users.destroy', 'parent_flag' => 'users.index',        'order' => 3],

    ['name' => 'Vai trò và phân quyền',  'flag' => 'roles.index',   'parent_flag' => 'group.system-admin', 'order' => 2],
    ['name' => 'Thêm',                   'flag' => 'roles.create',  'parent_flag' => 'roles.index',        'order' => 1],
    ['name' => 'Sửa',                    'flag' => 'roles.edit',    'parent_flag' => 'roles.index',        'order' => 2],
    ['name' => 'Xóa',                    'flag' => 'roles.destroy', 'parent_flag' => 'roles.index',        'order' => 3],
];
