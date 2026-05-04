<?php

/**
 * @apiGroup  Shift
 * @apiName   UpdateShiftDepartment
 *
 * @api {PATCH} /v1/admin/shifts/:id/departments/:department_id Update Shift Department
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\UpdateShiftDepartmentController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/shifts/{id}/departments/{department_id}', UpdateShiftDepartmentController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_department_update');
