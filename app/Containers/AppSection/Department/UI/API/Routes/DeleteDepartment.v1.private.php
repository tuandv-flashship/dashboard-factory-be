<?php

/**
 * @apiGroup  Department
 * @apiName   DeleteDepartment
 *
 * @api {DELETE} /v1/admin/departments/:id Delete Department
 */

use App\Containers\AppSection\Department\UI\API\Controllers\DeleteDepartmentController;
use Illuminate\Support\Facades\Route;

Route::delete('admin/departments/{id}', DeleteDepartmentController::class)
    ->middleware(['auth:api'])
    ->name('api_department_delete_department');
