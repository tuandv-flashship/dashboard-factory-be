<?php

return [
    ['name' => 'Audit Logs', 'flag' => 'audit-log.index',   'parent_flag' => 'group.system-admin', 'order' => 99, 'hidden' => true],
    ['name' => 'Xóa',       'flag' => 'audit-log.destroy', 'parent_flag' => 'audit-log.index',    'order' => 1,  'hidden' => true],
];
