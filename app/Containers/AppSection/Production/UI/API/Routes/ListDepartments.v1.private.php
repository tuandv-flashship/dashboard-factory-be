<?php

/**
 * @apiGroup  Production
 * @apiName   ListDepartments
 *
 * @api {GET} /v1/admin/departments List Departments
 */

use App\Containers\AppSection\Production\UI\API\Controllers\ListDepartmentsController;
use Illuminate\Support\Facades\Route;

Route::get('admin/departments', ListDepartmentsController::class)
    ->middleware(['auth:api'])
    ->name('api_production_list_departments');
