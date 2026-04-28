<?php

/**
 * @apiGroup  Shift
 * @apiName   FindHourlyRecord
 *
 * @api {GET} /v1/admin/hourly-records/:id Find Hourly Record
 *
 * Get a single hourly record by ID with issues included.
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\FindHourlyRecordController;
use Illuminate\Support\Facades\Route;

Route::get('admin/hourly-records/{id}', FindHourlyRecordController::class)
    ->middleware(['auth:api'])
    ->name('api_hourly_record_find');
