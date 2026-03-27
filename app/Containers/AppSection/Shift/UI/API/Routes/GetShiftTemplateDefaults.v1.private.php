<?php

/**
 * @apiGroup  ShiftTemplate
 * @apiName   GetShiftTemplateDefaults
 *
 * @api {GET} /v1/admin/shift-templates/defaults Get Default Shift Template Details
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\GetShiftTemplateDefaultsController;
use Illuminate\Support\Facades\Route;

Route::get('admin/shift-templates/defaults', GetShiftTemplateDefaultsController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_template_defaults');
