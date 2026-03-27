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
    ->where('id', '[0-9]+')
    ->middleware(['auth:api'])
    ->name('api_shift_find');
