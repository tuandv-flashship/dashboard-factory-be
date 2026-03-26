<?php

/**
 * @apiGroup  Department
 * @apiName   ListDepartments
 *
 * @api {GET} /v1/admin/departments List Departments
 */

use App\Containers\AppSection\Department\UI\API\Controllers\ListDepartmentsController;
use Illuminate\Support\Facades\Route;

Route::get('admin/departments', ListDepartmentsController::class)
    ->middleware(['auth:api'])
    ->name('api_department_list_departments');
