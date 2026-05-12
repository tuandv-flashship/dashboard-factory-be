<?php

return [
    ['name' => 'Reason Codes', 'flag' => 'reason-codes.index',   'parent_flag' => 'group.operation-admin',  'order' => 99, 'hidden' => true],
    ['name' => 'Thêm',        'flag' => 'reason-codes.create',  'parent_flag' => 'reason-codes.index',     'order' => 1,  'hidden' => true],
    ['name' => 'Sửa',         'flag' => 'reason-codes.edit',    'parent_flag' => 'reason-codes.index',     'order' => 2,  'hidden' => true],
    ['name' => 'Xóa',         'flag' => 'reason-codes.destroy', 'parent_flag' => 'reason-codes.index',     'order' => 3,  'hidden' => true],
];
