<?php

return [
    // ── Cài đặt ca chuẩn → Operation Admin ──
    ['name' => 'Cài đặt ca chuẩn', 'flag' => 'shift-templates.index',   'parent_flag' => 'group.operation-admin',    'order' => 1],
    ['name' => 'Thêm',             'flag' => 'shift-templates.create',  'parent_flag' => 'shift-templates.index',    'order' => 1],
    ['name' => 'Sửa',              'flag' => 'shift-templates.edit',    'parent_flag' => 'shift-templates.index',    'order' => 2],
    ['name' => 'Xóa',              'flag' => 'shift-templates.destroy', 'parent_flag' => 'shift-templates.index',    'order' => 3],

    // ── Lịch ca → Operation Admin ──
    ['name' => 'Lịch ca', 'flag' => 'shifts.index',   'parent_flag' => 'group.operation-admin', 'order' => 4],
    ['name' => 'Thêm',    'flag' => 'shifts.create',  'parent_flag' => 'shifts.index',          'order' => 1],
    ['name' => 'Sửa',     'flag' => 'shifts.edit',    'parent_flag' => 'shifts.index',          'order' => 2],
    ['name' => 'Xóa',     'flag' => 'shifts.destroy', 'parent_flag' => 'shifts.index',          'order' => 3],
];
