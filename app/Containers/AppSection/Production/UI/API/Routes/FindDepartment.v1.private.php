<?php

/**
 * @apiGroup  Production
 * @apiName   FindDepartment
 *
 * @api {GET} /v1/admin/departments/:id Find Department
 */

use App\Containers\AppSection\Production\UI\API\Controllers\FindDepartmentController;
use Illuminate\Support\Facades\Route;

Route::get('admin/departments/{id}', FindDepartmentController::class)
    ->middleware(['auth:api'])
    ->name('api_production_find_department');
