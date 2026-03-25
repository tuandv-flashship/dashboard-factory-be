<?php

/**
 * @apiGroup  Shift
 * @apiName   FindShift
 *
 * @api {GET} /v1/admin/shifts/:id Find Shift
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\FindShiftController;
use Illuminate\Support\Facades\Route;

Route::get('admin/shifts/{id}', FindShiftController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_find');
