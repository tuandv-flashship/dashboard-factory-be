<?php

/**
 * @apiGroup  Shift
 * @apiName   CreateDailyShift
 *
 * @api {POST} /v1/admin/shifts/create-daily Create Daily Shift (Auto)
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\CreateDailyShiftController;
use Illuminate\Support\Facades\Route;

Route::post('admin/shifts/create-daily', CreateDailyShiftController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_create_daily');
