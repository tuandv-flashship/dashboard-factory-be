<?php

/**
 * @apiGroup  Production
 * @apiName   GetAllLinesHourlyPublic
 *
 * @api {GET} /v1/production/hourly All Lines Hourly Records (Public)
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetAllLinesHourlyController;
use Illuminate\Support\Facades\Route;

Route::get('production/hourly', GetAllLinesHourlyController::class)
    ->middleware('throttle:60,1');
