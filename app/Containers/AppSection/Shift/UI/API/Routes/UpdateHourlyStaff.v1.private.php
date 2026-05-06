<?php

/**
 * @apiGroup  Shift
 * @apiName   UpdateHourlyRecords
 *
 * @api {PATCH} /v1/admin/shifts/:id/hourly Update Hourly Records
 *
 * Batch update hourly records: kpi_minutes, target, staff_required, note,
 * machine_count (DTF), active_machine_ids (DTG).
 *
 * Side effects:
 *   kpi_minutes → auto-recalculates kpi_hours and kpi_percent.
 *   target → cascades hour_start_inventory for subsequent slots.
 *   machine_count (DTF only) → multiplier fallback for target estimation.
 *   active_machine_ids (DTG only) → sync hourly_record_machines pivot,
 *     auto-update machine_count and target from Σ(machine KPIs).
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\UpdateHourlyStaffController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/shifts/{id}/hourly', UpdateHourlyStaffController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_hourly_update');
