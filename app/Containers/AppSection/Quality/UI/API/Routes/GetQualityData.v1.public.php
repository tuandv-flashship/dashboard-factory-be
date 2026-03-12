<?php

/**
 * @apiGroup  Quality
 * @apiName   GetQualityDataPublic
 *
 * @api {GET} /v1/quality Get Quality Data (Public)
 */

use App\Containers\AppSection\Quality\UI\API\Controllers\GetQualityDataController;
use Illuminate\Support\Facades\Route;

Route::get('quality', GetQualityDataController::class)
    ->middleware('throttle:60,1');
