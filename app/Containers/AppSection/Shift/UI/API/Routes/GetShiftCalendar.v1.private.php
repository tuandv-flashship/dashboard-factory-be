<?php

/**
 * @apiGroup  Shift
 * @apiName   GetShiftCalendar
 *
 * @api {GET} /v1/admin/shifts/calendar Get Shift Calendar
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\GetShiftCalendarController;
use Illuminate\Support\Facades\Route;

Route::get('admin/shifts/calendar', GetShiftCalendarController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_calendar');
