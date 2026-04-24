<?php

/**
 * @apiGroup  Shift
 * @apiName   UpdateSingleHourlyRecord
 *
 * @api {PATCH} /v1/admin/hourly-records/:id Update Single Hourly Record
 *
 * Update a single hourly record by ID.
 * Accepts: kpi_minutes, target, staff_required, note.
 * Side effects: kpi_minutes → auto-recalc kpi_hours/kpi_percent.
 *               target → cascade hour_start_inventory for subsequent slots.
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\UpdateSingleHourlyRecordController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/hourly-records/{id}', UpdateSingleHourlyRecordController::class)
    ->middleware(['auth:api'])
    ->name('api_hourly_record_update');
