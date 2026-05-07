<?php

return [
    ['name' => 'Departments', 'flag' => 'departments.index',   'parent_flag' => 'settings.common',    'order' => 1],
    ['name' => 'Create',      'flag' => 'departments.create',  'parent_flag' => 'departments.index', 'order' => 1],
    ['name' => 'Edit',        'flag' => 'departments.edit',    'parent_flag' => 'departments.index', 'order' => 2],
    ['name' => 'Delete',      'flag' => 'departments.destroy', 'parent_flag' => 'departments.index', 'order' => 3],
];
