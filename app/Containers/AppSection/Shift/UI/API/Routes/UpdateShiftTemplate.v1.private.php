<?php

/**
 * @apiGroup  ShiftTemplate
 * @apiName   UpdateShiftTemplate
 *
 * @api {PATCH} /v1/admin/shift-templates/:id Update Shift Template
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\UpdateShiftTemplateController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/shift-templates/{id}', UpdateShiftTemplateController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_template_update');
