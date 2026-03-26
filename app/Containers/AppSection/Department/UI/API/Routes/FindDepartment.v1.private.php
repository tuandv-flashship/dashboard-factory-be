<?php

/**
 * @apiGroup  Department
 * @apiName   FindDepartment
 *
 * @api {GET} /v1/admin/departments/:id Find Department
 */

use App\Containers\AppSection\Department\UI\API\Controllers\FindDepartmentController;
use Illuminate\Support\Facades\Route;

Route::get('admin/departments/{id}', FindDepartmentController::class)
    ->middleware(['auth:api'])
    ->name('api_department_find_department');
