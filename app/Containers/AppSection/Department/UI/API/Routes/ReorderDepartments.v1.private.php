<?php

/**
 * @apiGroup  Department
 * @apiName   ReorderDepartments
 *
 * @api {PATCH} /v1/admin/departments/reorder Reorder Departments
 */

use App\Containers\AppSection\Department\UI\API\Controllers\ReorderDepartmentsController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/departments/reorder', ReorderDepartmentsController::class)
    ->middleware(['auth:api'])
    ->name('api_department_reorder_departments');
