<?php

/**
 * @apiGroup  Production
 * @apiName   GetAllLinesHourly
 *
 * @api {GET} /v1/admin/production/hourly All Lines Hourly Records (Private)
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetAllLinesHourlyController;
use Illuminate\Support\Facades\Route;

Route::get('admin/production/hourly', GetAllLinesHourlyController::class)
    ->middleware(['auth:api', 'throttle:60,1']);
