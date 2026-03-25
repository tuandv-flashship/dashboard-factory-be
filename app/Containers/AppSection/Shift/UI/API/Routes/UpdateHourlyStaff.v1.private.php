<?php

/**
 * @apiGroup  Shift
 * @apiName   UpdateHourlyStaff
 *
 * @api {PATCH} /v1/admin/shifts/:id/hourly Update Hourly Staff
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\UpdateHourlyStaffController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/shifts/{id}/hourly', UpdateHourlyStaffController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_hourly_update');
