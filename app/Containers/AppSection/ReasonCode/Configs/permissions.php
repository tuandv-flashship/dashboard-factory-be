<?php

return [
    [
        'name'        => 'Reason Codes',
        'flag'        => 'reason-codes.index',
        'parent_flag' => 'core.system',
    ],
    [
        'name'        => 'Create',
        'flag'        => 'reason-codes.create',
        'parent_flag' => 'reason-codes.index',
    ],
    [
        'name'        => 'Edit',
        'flag'        => 'reason-codes.edit',
        'parent_flag' => 'reason-codes.index',
    ],
    [
        'name'        => 'Delete',
        'flag'        => 'reason-codes.destroy',
        'parent_flag' => 'reason-codes.index',
    ],
];
