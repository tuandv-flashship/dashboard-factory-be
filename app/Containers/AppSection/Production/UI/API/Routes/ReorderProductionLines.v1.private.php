<?php

/**
 * @apiGroup  Production
 * @apiName   ReorderProductionLines
 *
 * @api {PATCH} /v1/admin/production-lines/reorder Reorder Production Lines
 */

use App\Containers\AppSection\Production\UI\API\Controllers\ReorderProductionLinesController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/production-lines/reorder', ReorderProductionLinesController::class)
    ->middleware(['auth:api'])
    ->name('api_production_reorder_production_lines');
