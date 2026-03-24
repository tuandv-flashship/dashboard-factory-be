<?php

/**
 * @apiGroup  Production
 * @apiName   DeleteProductionLine
 *
 * @api {DELETE} /v1/admin/production-lines/:id Delete Production Line
 */

use App\Containers\AppSection\Production\UI\API\Controllers\DeleteProductionLineController;
use Illuminate\Support\Facades\Route;

Route::delete('admin/production-lines/{id}', DeleteProductionLineController::class)
    ->middleware(['auth:api'])
    ->name('api_production_delete_production_line');
