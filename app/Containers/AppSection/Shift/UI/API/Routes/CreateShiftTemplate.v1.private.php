<?php

/**
 * @apiGroup  ShiftTemplate
 * @apiName   CreateShiftTemplate
 *
 * @api {POST} /v1/admin/shift-templates Create Shift Template
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\CreateShiftTemplateController;
use Illuminate\Support\Facades\Route;

Route::post('admin/shift-templates', CreateShiftTemplateController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_template_create');
