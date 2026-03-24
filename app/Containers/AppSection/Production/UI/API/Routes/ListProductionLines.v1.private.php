<?php

/**
 * @apiGroup  Production
 * @apiName   ListProductionLines
 *
 * @api {GET} /v1/admin/production-lines List Production Lines
 */

use App\Containers\AppSection\Production\UI\API\Controllers\ListProductionLinesController;
use Illuminate\Support\Facades\Route;

Route::get('admin/production-lines', ListProductionLinesController::class)
    ->middleware(['auth:api'])
    ->name('api_production_list_production_lines');
