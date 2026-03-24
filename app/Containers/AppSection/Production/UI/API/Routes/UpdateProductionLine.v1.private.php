<?php

/**
 * @apiGroup  Production
 * @apiName   UpdateProductionLine
 *
 * @api {PATCH} /v1/admin/production-lines/:id Update Production Line
 */

use App\Containers\AppSection\Production\UI\API\Controllers\UpdateProductionLineController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/production-lines/{id}', UpdateProductionLineController::class)
    ->middleware(['auth:api'])
    ->name('api_production_update_production_line');
