<?php

/**
 * @apiGroup  Shift
 * @apiName   DeleteLastHourlyRecord
 *
 * @api {DELETE} /v1/admin/shifts/:shift_id/departments/:department_id/hourly Delete Last Hourly Record
 *
 * Soft-deletes the last hourly slot for a department.
 * Auto-decrements shift_details.work_hours by 1 hour (min 0).
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\DeleteLastHourlyRecordController;
use Illuminate\Support\Facades\Route;

Route::delete('admin/shifts/{shift_id}/departments/{department_id}/hourly', DeleteLastHourlyRecordController::class)
    ->middleware(['auth:api'])
    ->name('api_hourly_record_delete_last');
