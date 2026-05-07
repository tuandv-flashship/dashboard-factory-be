<?php

return [
    // ── Production Dashboard → Department group ───
    ['name' => 'Production Dashboard', 'flag' => 'production.view', 'parent_flag' => 'department.scope', 'order' => 2],
    ['name' => 'CRUD Dashboard',       'flag' => 'production.crud', 'parent_flag' => 'production.view', 'order' => 1],

    // ── Production Lines → Settings group ──
    ['name' => 'Production Lines', 'flag' => 'production-lines.index',   'parent_flag' => 'settings.common', 'order' => 2],
    ['name' => 'Create',           'flag' => 'production-lines.create',  'parent_flag' => 'production-lines.index', 'order' => 1],
    ['name' => 'Edit',             'flag' => 'production-lines.edit',    'parent_flag' => 'production-lines.index', 'order' => 2],
    ['name' => 'Delete',           'flag' => 'production-lines.destroy', 'parent_flag' => 'production-lines.index', 'order' => 3],

    // ── Hourly Issues → Department group ─────
    ['name' => 'Hourly Issues', 'flag' => 'hourly-issues.index',   'parent_flag' => 'department.scope', 'order' => 3],
    ['name' => 'Create',        'flag' => 'hourly-issues.create',  'parent_flag' => 'hourly-issues.index', 'order' => 1],
    ['name' => 'Edit',          'flag' => 'hourly-issues.edit',    'parent_flag' => 'hourly-issues.index', 'order' => 2],
    ['name' => 'Delete',        'flag' => 'hourly-issues.destroy', 'parent_flag' => 'hourly-issues.index', 'order' => 3],
    ['name' => 'Resolve',       'flag' => 'hourly-issues.resolve', 'parent_flag' => 'hourly-issues.index', 'order' => 4],
];
