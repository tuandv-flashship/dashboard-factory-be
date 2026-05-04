<?php

/**
 * @apiGroup  Shift
 * @apiName   GetShiftDepartment
 *
 * @api {GET} /v1/admin/shifts/:id/departments/:department_id Get Shift Department Detail
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\GetShiftDepartmentController;
use Illuminate\Support\Facades\Route;

Route::get('admin/shifts/{id}/departments/{department_id}', GetShiftDepartmentController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_department_show');
