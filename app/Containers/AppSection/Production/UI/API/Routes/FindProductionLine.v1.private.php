<?php

/**
 * @apiGroup  Production
 * @apiName   FindProductionLine
 *
 * @api {GET} /v1/admin/production-lines/:id Find Production Line
 */

use App\Containers\AppSection\Production\UI\API\Controllers\FindProductionLineController;
use Illuminate\Support\Facades\Route;

Route::get('admin/production-lines/{id}', FindProductionLineController::class)
    ->middleware(['auth:api'])
    ->name('api_production_find_production_line');
