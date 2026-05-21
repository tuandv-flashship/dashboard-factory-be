<?php

/**
 * @apiGroup  Production
 * @apiName   GetAllLinesHourly
 *
 * @api {GET} /v1/production/hourly All Lines Hourly Records
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetAllLinesHourlyController;
use Illuminate\Support\Facades\Route;

Route::get('production/hourly', GetAllLinesHourlyController::class)
    ->middleware(['auth:api', 'throttle:60,1']);

