<?php

return [
    [
        'name' => 'System',
        'flag' => 'core.system',
    ],
    [
        'name' => 'CMS',
        'flag' => 'core.cms',
    ],
    [
        'name' => 'Settings',
        'flag' => 'settings.common',
    ],
    [
        'name' => 'Cronjob',
        'flag' => 'systems.cronjob',
        'parent_flag' => 'core.system',
    ],
    [
        'name' => 'Tools',
        'flag' => 'core.tools',
    ],
    [
        'name' => 'Import/Export Data',
        'flag' => 'tools.data-synchronize',
        'parent_flag' => 'core.tools',
    ],
];
