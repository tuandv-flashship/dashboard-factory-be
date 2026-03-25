<?php

return [
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
];
