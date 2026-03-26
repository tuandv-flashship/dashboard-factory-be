<?php

/**
 * @apiGroup  Department
 * @apiName   UpdateDepartment
 *
 * @api {PATCH} /v1/admin/departments/:id Update Department
 */

use App\Containers\AppSection\Department\UI\API\Controllers\UpdateDepartmentController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/departments/{id}', UpdateDepartmentController::class)
    ->middleware(['auth:api'])
    ->name('api_department_update_department');
