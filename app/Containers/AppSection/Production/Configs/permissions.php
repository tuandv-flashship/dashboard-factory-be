<?php

return [
    // ── Dashboard → Department Data group ───
    ['name' => 'Dashboard',                    'flag' => 'dashboard.view',              'parent_flag' => 'group.department-data', 'order' => 1, 'is_department_scopeable' => true],
    ['name' => 'Xem dữ liệu sản xuất',        'flag' => 'dashboard.view-productivity', 'parent_flag' => 'dashboard.view',        'order' => 1],

    // ── Giờ làm việc → Department Data group ──
    ['name' => 'Giờ làm việc', 'flag' => 'working-hours.index',   'parent_flag' => 'group.department-data',  'order' => 2, 'is_department_scopeable' => true],
    ['name' => 'Thêm',         'flag' => 'working-hours.create',  'parent_flag' => 'working-hours.index',    'order' => 1],
    ['name' => 'Sửa',          'flag' => 'working-hours.edit',    'parent_flag' => 'working-hours.index',    'order' => 2],
    ['name' => 'Xóa',          'flag' => 'working-hours.destroy', 'parent_flag' => 'working-hours.index',    'order' => 3],

    // ── Lý do miss KPI → Department Data group ──
    ['name' => 'Lý do miss KPI', 'flag' => 'hourly-issues.index',   'parent_flag' => 'group.department-data', 'order' => 3, 'is_department_scopeable' => true],
    ['name' => 'Thêm',           'flag' => 'hourly-issues.create',  'parent_flag' => 'hourly-issues.index',   'order' => 1],
    ['name' => 'Sửa',            'flag' => 'hourly-issues.edit',    'parent_flag' => 'hourly-issues.index',   'order' => 2],
    ['name' => 'Xóa',            'flag' => 'hourly-issues.destroy', 'parent_flag' => 'hourly-issues.index',   'order' => 3],

    // ── Xác nhận nhân sự → Department Data group ──
    ['name' => 'Xác nhận nhân sự', 'flag' => 'attendance.index', 'parent_flag' => 'group.department-data', 'order' => 4, 'is_department_scopeable' => true],
    ['name' => 'Sửa',              'flag' => 'attendance.edit',  'parent_flag' => 'attendance.index',      'order' => 1],

    // ── Production Lines → Hidden ──
    ['name' => 'Production Lines', 'flag' => 'production-lines.index',   'parent_flag' => 'group.operation-admin',   'order' => 99, 'hidden' => true],
    ['name' => 'Thêm',            'flag' => 'production-lines.create',  'parent_flag' => 'production-lines.index',  'order' => 1,  'hidden' => true],
    ['name' => 'Sửa',             'flag' => 'production-lines.edit',    'parent_flag' => 'production-lines.index',  'order' => 2,  'hidden' => true],
    ['name' => 'Xóa',             'flag' => 'production-lines.destroy', 'parent_flag' => 'production-lines.index',  'order' => 3,  'hidden' => true],
];
