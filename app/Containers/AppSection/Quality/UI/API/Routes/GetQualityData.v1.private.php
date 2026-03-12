<?php

/**
 * @apiGroup  Quality
 * @apiName   GetQualityData
 *
 * @api {GET} /v1/quality Get Quality Data
 *
 * @apiDescription Get quality inspection data for the current shift.
 */

use App\Containers\AppSection\Quality\UI\API\Controllers\GetQualityDataController;
use Illuminate\Support\Facades\Route;

Route::get('quality', GetQualityDataController::class)
    ->middleware(['auth:api']);
