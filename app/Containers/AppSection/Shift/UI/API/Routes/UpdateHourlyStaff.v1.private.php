<?php

/**
 * @apiGroup  Shift
 * @apiName   UpdateHourlyRecords
 *
 * @api {PATCH} /v1/admin/shifts/:id/hourly Update Hourly Records
 *
 * Batch update hourly records: kpi_minutes, target, staff_required, note.
 * When kpi_minutes changes → auto-recalculates kpi_hours and kpi_percent.
 * When target changes → cascades hour_start_inventory for subsequent slots.
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\UpdateHourlyStaffController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/shifts/{id}/hourly', UpdateHourlyStaffController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_hourly_update');
