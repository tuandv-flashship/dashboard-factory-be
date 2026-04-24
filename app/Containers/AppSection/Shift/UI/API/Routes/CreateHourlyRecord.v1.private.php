<?php

/**
 * @apiGroup  Shift
 * @apiName   CreateHourlyRecord
 *
 * @api {POST} /v1/admin/shifts/:shift_id/departments/:department_id/hourly Create Hourly Record
 *
 * Append a new hourly slot after the last one for a department.
 * Auto-increments shift_details.work_hours by 1 hour.
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\CreateHourlyRecordController;
use Illuminate\Support\Facades\Route;

Route::post('admin/shifts/{shift_id}/departments/{department_id}/hourly', CreateHourlyRecordController::class)
    ->middleware(['auth:api'])
    ->name('api_hourly_record_create');
