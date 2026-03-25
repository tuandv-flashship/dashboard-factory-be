<?php

/**
 * @apiGroup  Shift
 * @apiName   UpdateShift
 *
 * @api {PATCH} /v1/admin/shifts/:id Update Shift
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\UpdateShiftController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/shifts/{id}', UpdateShiftController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_update');
