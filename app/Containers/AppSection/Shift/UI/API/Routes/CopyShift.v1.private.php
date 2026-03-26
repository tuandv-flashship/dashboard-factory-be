<?php

/**
 * @apiGroup  Shift
 * @apiName   CopyShift
 *
 * @api {POST} /v1/admin/shifts/copy Copy Shifts to Other Dates
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\CopyShiftController;
use Illuminate\Support\Facades\Route;

Route::post('admin/shifts/copy', CopyShiftController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_copy');
