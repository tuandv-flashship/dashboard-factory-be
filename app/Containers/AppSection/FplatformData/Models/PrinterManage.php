<?php

namespace App\Containers\AppSection\FplatformData\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only model for fplatform.printer_manage.
 */
final class PrinterManage extends Model
{
    protected $connection = 'fplatform';

    protected $table = 'printer_manage';

    public $timestamps = false;
}
