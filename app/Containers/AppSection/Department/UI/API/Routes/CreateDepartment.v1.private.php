<?php

/**
 * @apiGroup  Department
 * @apiName   CreateDepartment
 *
 * @api {POST} /v1/admin/departments Create Department
 */

use App\Containers\AppSection\Department\UI\API\Controllers\CreateDepartmentController;
use Illuminate\Support\Facades\Route;

Route::post('admin/departments', CreateDepartmentController::class)
    ->middleware(['auth:api'])
    ->name('api_department_create_department');
