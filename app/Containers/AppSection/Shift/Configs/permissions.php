<?php

return [
    // ── Shift Templates → Settings group ──
    ['name' => 'Shift Templates', 'flag' => 'shift-templates.index', 'parent_flag' => 'settings.common', 'order' => 5],
    ['name' => 'Create',          'flag' => 'shift-templates.create', 'parent_flag' => 'shift-templates.index', 'order' => 1],
    ['name' => 'Edit',            'flag' => 'shift-templates.edit',   'parent_flag' => 'shift-templates.index', 'order' => 2],
    ['name' => 'Delete',          'flag' => 'shift-templates.destroy', 'parent_flag' => 'shift-templates.index', 'order' => 3],

    // ── Shifts → Department group ──
    ['name' => 'Shifts',  'flag' => 'shifts.index',   'parent_flag' => 'department.scope', 'order' => 1],
    ['name' => 'Create',  'flag' => 'shifts.create',  'parent_flag' => 'shifts.index', 'order' => 1],
    ['name' => 'Edit',    'flag' => 'shifts.edit',    'parent_flag' => 'shifts.index', 'order' => 2],
    ['name' => 'Delete',  'flag' => 'shifts.destroy', 'parent_flag' => 'shifts.index', 'order' => 3],
];
