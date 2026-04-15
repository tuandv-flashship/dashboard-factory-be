<?php

/**
 * @apiGroup  Production
 * @apiName   ResyncHourlyRecords
 *
 * @api {POST} /v1/admin/production/resync Resync Hourly Records
 */

use App\Containers\AppSection\Production\UI\API\Controllers\ResyncHourlyRecordsController;
use Illuminate\Support\Facades\Route;

Route::post('admin/production/resync', ResyncHourlyRecordsController::class)
    ->middleware(['auth:api']);
