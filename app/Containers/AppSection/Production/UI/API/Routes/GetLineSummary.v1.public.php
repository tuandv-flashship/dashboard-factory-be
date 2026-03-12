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
    ->where('line', 'dtf1|dtf2|dtg')
    ->middleware('throttle:60,1');
