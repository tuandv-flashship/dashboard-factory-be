<?php

/**
 * @apiGroup  Production
 * @apiName   ReorderDepartments
 *
 * @api {PATCH} /v1/admin/departments/reorder Reorder Departments
 */

use App\Containers\AppSection\Production\UI\API\Controllers\ReorderDepartmentsController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/departments/reorder', ReorderDepartmentsController::class)
    ->middleware(['auth:api'])
    ->name('api_production_reorder_departments');
