<?php

/**
 * @apiGroup  Production
 * @apiName   GetAllProductionLinesPublic
 *
 * @api {GET} /v1/production/lines Get All Production Lines (Public)
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetAllProductionLinesController;
use Illuminate\Support\Facades\Route;

Route::get('production/lines', GetAllProductionLinesController::class)
    ->middleware('throttle:60,1');
