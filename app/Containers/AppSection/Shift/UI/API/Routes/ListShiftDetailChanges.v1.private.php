<?php

/**
 * @apiGroup  Shift
 * @apiName   ListShiftDetailChanges
 *
 * @api {GET} /v1/admin/shift/shift-details/:shift_detail_id/changes List Shift Detail Changes
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\ListShiftDetailChangesController;
use Illuminate\Support\Facades\Route;

Route::get('admin/shift/shift-details/{shift_detail_id}/changes', ListShiftDetailChangesController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_list_shift_detail_changes');
