<?php

/**
 * @apiGroup  ShiftTemplate
 * @apiName   ReorderShiftTemplates
 *
 * @api {PATCH} /v1/admin/shift-templates/reorder Reorder Shift Templates
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\ReorderShiftTemplatesController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/shift-templates/reorder', ReorderShiftTemplatesController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_template_reorder');
