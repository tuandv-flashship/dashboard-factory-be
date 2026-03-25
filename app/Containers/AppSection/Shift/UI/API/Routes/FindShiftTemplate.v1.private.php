<?php

/**
 * @apiGroup  ShiftTemplate
 * @apiName   FindShiftTemplate
 *
 * @api {GET} /v1/admin/shift-templates/:id Find Shift Template
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\FindShiftTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('admin/shift-templates/{id}', FindShiftTemplateController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_template_find');
