<?php

/**
 * @apiGroup  ShiftTemplate
 * @apiName   CopyShiftTemplate
 *
 * @api {POST} /v1/admin/shift-templates/:id/copy Copy Shift Template
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\CopyShiftTemplateController;
use Illuminate\Support\Facades\Route;

Route::post('admin/shift-templates/{id}/copy', CopyShiftTemplateController::class)
    ->middleware(['auth:api'])
    ->name('api_shift_template_copy');
