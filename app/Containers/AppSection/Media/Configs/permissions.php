<?php

return [
    ['name' => 'Media',  'flag' => 'media.index',    'parent_flag' => 'group.system-admin', 'order' => 99, 'hidden' => true],

    ['name' => 'File',   'flag' => 'files.index',    'parent_flag' => 'media.index',   'order' => 1, 'hidden' => true],
    ['name' => 'Thêm',  'flag' => 'files.create',   'parent_flag' => 'files.index',   'order' => 1, 'hidden' => true],
    ['name' => 'Sửa',   'flag' => 'files.edit',     'parent_flag' => 'files.index',   'order' => 2, 'hidden' => true],
    ['name' => 'Trash',  'flag' => 'files.trash',    'parent_flag' => 'files.index',   'order' => 3, 'hidden' => true],
    ['name' => 'Xóa',   'flag' => 'files.destroy',  'parent_flag' => 'files.index',   'order' => 4, 'hidden' => true],

    ['name' => 'Folder', 'flag' => 'folders.index',  'parent_flag' => 'media.index',   'order' => 2, 'hidden' => true],
    ['name' => 'Thêm',  'flag' => 'folders.create', 'parent_flag' => 'folders.index', 'order' => 1, 'hidden' => true],
    ['name' => 'Sửa',   'flag' => 'folders.edit',   'parent_flag' => 'folders.index', 'order' => 2, 'hidden' => true],
    ['name' => 'Trash',  'flag' => 'folders.trash',  'parent_flag' => 'folders.index', 'order' => 3, 'hidden' => true],
    ['name' => 'Xóa',   'flag' => 'folders.destroy', 'parent_flag' => 'folders.index', 'order' => 4, 'hidden' => true],
];
