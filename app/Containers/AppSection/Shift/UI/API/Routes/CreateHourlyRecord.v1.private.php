<?php

/**
 * @apiGroup  Shift
 * @apiName   CreateHourlyRecord
 *
 * @api {POST} /v1/admin/shifts/:shift_id/departments/:department_id/hourly Create Hourly Record
 *
 * Append a new hourly slot after the last one for a department.
 * Auto-increments shift_details.work_hours by 1 hour.
 * Accepts: kpi_minutes, target, staff_required, note, machine_count, active_machine_ids.
 *
 * Side effects:
 *   machine_count (DTF/DTG only) → stored directly as multiplier.
 *   active_machine_ids (DTG only) → sync hourly_record_machines pivot,
 *     auto-set machine_count = count(machines) and target = Σ(KPIs) × kpi_percent.
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\CreateHourlyRecordController;
use Illuminate\Support\Facades\Route;

Route::post('admin/shifts/{shift_id}/departments/{department_id}/hourly', CreateHourlyRecordController::class)
    ->middleware(['auth:api'])
    ->name('api_hourly_record_create');
