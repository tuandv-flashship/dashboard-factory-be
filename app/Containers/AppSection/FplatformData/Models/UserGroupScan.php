<?php

namespace App\Containers\AppSection\FplatformData\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only model for fplatform.user_group_scan.
 */
final class UserGroupScan extends Model
{
    protected $connection = 'fplatform';

    protected $table = 'user_group_scan';

    public $timestamps = false;

    protected $casts = [
        'work_type'   => 'integer',
        'work_status' => 'integer',
    ];
}
