<?php

namespace App\Containers\AppSection\FplatformData\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only model for fplatform.folder_manage.
 * No migrations — this table is managed by the fplatform system.
 */
final class FolderManage extends Model
{
    protected $connection = 'fplatform';

    protected $table = 'folder_manage';

    public $timestamps = false;

    protected $casts = [
        'estimate_date' => 'date',
        'total_file'    => 'integer',
        'total_product' => 'integer',
        'status_folder' => 'integer',
    ];
}
