<?php

/**
 * @apiGroup  ShiftTemplate
 * @apiName   ListShiftTemplates
 *
 * @api {GET} /v1/admin/shift-templates List Shift Templates
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\ListShiftTemplatesController;
use Illuminate\Support\Facades\Route;

Route::get('admin/shift-templates', ListShiftTemplatesController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_template_list');
