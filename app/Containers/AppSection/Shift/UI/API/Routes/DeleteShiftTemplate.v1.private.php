<?php

/**
 * @apiGroup  ShiftTemplate
 * @apiName   DeleteShiftTemplate
 *
 * @api {DELETE} /v1/admin/shift-templates/:id Delete Shift Template
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\DeleteShiftTemplateController;
use Illuminate\Support\Facades\Route;

Route::delete('admin/shift-templates/{id}', DeleteShiftTemplateController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_template_delete');
