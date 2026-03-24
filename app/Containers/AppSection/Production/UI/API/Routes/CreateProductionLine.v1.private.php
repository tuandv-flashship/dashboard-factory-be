<?php

/**
 * @apiGroup  Production
 * @apiName   CreateProductionLine
 *
 * @api {POST} /v1/admin/production-lines Create Production Line
 */

use App\Containers\AppSection\Production\UI\API\Controllers\CreateProductionLineController;
use Illuminate\Support\Facades\Route;

Route::post('admin/production-lines', CreateProductionLineController::class)
    ->middleware(['auth:api'])
    ->name('api_production_create_production_line');
