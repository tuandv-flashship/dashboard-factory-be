<?php

/**
 * @apiGroup  Shift
 * @apiName   DeleteShift
 *
 * @api {DELETE} /v1/admin/shifts/:id Delete Shift
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\DeleteShiftController;
use Illuminate\Support\Facades\Route;

Route::delete('admin/shifts/{id}', DeleteShiftController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_delete');
