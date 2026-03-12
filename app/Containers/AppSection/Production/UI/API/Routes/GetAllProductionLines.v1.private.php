<?php

/**
 * @apiGroup  Production
 * @apiName   GetAllProductionLines
 *
 * @api {GET} /v1/production/lines Get All Production Lines
 *
 * @apiDescription Get all active production lines with their departments.
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetAllProductionLinesController;
use Illuminate\Support\Facades\Route;

Route::get('production/lines', GetAllProductionLinesController::class)
    ->middleware(['auth:api']);
