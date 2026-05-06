<?php

/**
 * @apiGroup  Shift
 * @apiName   UpdateSingleHourlyRecord
 *
 * @api {PATCH} /v1/admin/hourly-records/:id Update Single Hourly Record
 *
 * Update a single hourly record by ID.
 * Accepts: kpi_minutes, target, staff_required, note, machine_count, active_machine_ids.
 *
 * Side effects:
 *   kpi_minutes → auto-recalc kpi_hours/kpi_percent.
 *   target → cascade hour_start_inventory for subsequent slots.
 *   machine_count (DTF only) → used as multiplier fallback for target.
 *   active_machine_ids (DTG only) → sync hourly_record_machines pivot,
 *     auto-update machine_count = count(machines) and target = Σ(KPIs) × kpi_percent.
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\UpdateSingleHourlyRecordController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/hourly-records/{id}', UpdateSingleHourlyRecordController::class)
    ->middleware(['auth:api'])
    ->name('api_hourly_record_update');
