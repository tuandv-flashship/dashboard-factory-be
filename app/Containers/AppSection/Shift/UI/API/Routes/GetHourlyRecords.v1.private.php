<?php

/**
 * @apiGroup  Shift
 * @apiName   GetHourlyRecords
 *
 * @api {GET} /v1/admin/shifts/:id/hourly Get Hourly Records
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\GetHourlyRecordsController;
use Illuminate\Support\Facades\Route;

Route::get('admin/shifts/{id}/hourly', GetHourlyRecordsController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_hourly_get');
