<?php

return [
    // ── Shift Templates ──────────────────────────────────
    [
        'name'        => 'Shift Templates',
        'flag'        => 'shift-templates.index',
        'parent_flag' => 'core.system',
    ],
    [
        'name'        => 'Create',
        'flag'        => 'shift-templates.create',
        'parent_flag' => 'shift-templates.index',
    ],
    [
        'name'        => 'Edit',
        'flag'        => 'shift-templates.edit',
        'parent_flag' => 'shift-templates.index',
    ],
    [
        'name'        => 'Delete',
        'flag'        => 'shift-templates.destroy',
        'parent_flag' => 'shift-templates.index',
    ],

    // ── Shifts (Ca làm việc) ─────────────────────────────
    [
        'name'        => 'Shifts',
        'flag'        => 'shifts.index',
        'parent_flag' => 'core.system',
    ],
    [
        'name'        => 'Create',
        'flag'        => 'shifts.create',
        'parent_flag' => 'shifts.index',
    ],
    [
        'name'        => 'Edit',
        'flag'        => 'shifts.edit',
        'parent_flag' => 'shifts.index',
    ],
    [
        'name'        => 'Delete',
        'flag'        => 'shifts.destroy',
        'parent_flag' => 'shifts.index',
    ],
];

