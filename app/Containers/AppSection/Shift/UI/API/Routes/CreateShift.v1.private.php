<?php

/**
 * @apiGroup  Shift
 * @apiName   CreateShift
 *
 * @api {POST} /v1/admin/shifts Create Shift
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\CreateShiftController;
use Illuminate\Support\Facades\Route;

Route::post('admin/shifts', CreateShiftController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_create');
