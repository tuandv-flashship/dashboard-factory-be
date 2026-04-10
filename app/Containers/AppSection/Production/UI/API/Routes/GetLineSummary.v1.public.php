<?php

/**
 * @apiGroup  Production
 * @apiName   GetLineSummaryPublic
 *
 * @api {GET} /v1/production/lines/:line Get Line Summary (Public)
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetLineSummaryController;
use Illuminate\Support\Facades\Route;

Route::get('production/lines/{line}', GetLineSummaryController::class)
    ->where('line', '[a-z_]+')
    ->middleware('throttle:60,1');
