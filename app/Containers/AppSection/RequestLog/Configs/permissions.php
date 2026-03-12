<?php

return [
    [
        'name' => 'Request Logs',
        'flag' => 'request-log.index',
        'parent_flag' => 'settings.common',
    ],
    [
        'name' => 'Delete',
        'flag' => 'request-log.destroy',
        'parent_flag' => 'request-log.index',
    ],
];
