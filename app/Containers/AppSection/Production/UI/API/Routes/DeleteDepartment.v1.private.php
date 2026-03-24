<?php

/**
 * @apiGroup  Production
 * @apiName   DeleteDepartment
 *
 * @api {DELETE} /v1/admin/departments/:id Delete Department
 */

use App\Containers\AppSection\Production\UI\API\Controllers\DeleteDepartmentController;
use Illuminate\Support\Facades\Route;

Route::delete('admin/departments/{id}', DeleteDepartmentController::class)
    ->middleware(['auth:api'])
    ->name('api_production_delete_department');
