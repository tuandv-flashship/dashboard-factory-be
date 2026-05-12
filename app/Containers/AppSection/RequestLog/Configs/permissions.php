<?php

return [
    ['name' => 'Request Logs', 'flag' => 'request-log.index',   'parent_flag' => 'group.system-admin', 'order' => 99, 'hidden' => true],
    ['name' => 'Xóa',         'flag' => 'request-log.destroy', 'parent_flag' => 'request-log.index',  'order' => 1,  'hidden' => true],
];
