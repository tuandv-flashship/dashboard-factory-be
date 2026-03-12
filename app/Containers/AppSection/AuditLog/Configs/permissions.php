<?php

return [
    [
        'name' => 'Audit Logs',
        'flag' => 'audit-log.index',
        'parent_flag' => 'settings.common',
    ],
    [
        'name' => 'Delete',
        'flag' => 'audit-log.destroy',
        'parent_flag' => 'audit-log.index',
    ],
];
