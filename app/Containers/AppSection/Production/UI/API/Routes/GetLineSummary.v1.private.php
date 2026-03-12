<?php

/**
 * @apiGroup  Production
 * @apiName   GetLineSummary
 *
 * @api {GET} /v1/production/lines/:line Get Line Summary
 *
 * @apiDescription Get production summary for a specific line including all departments and pick data.
 *
 * @apiParam {String="dtf1","dtf2","dtg"} line Production line code
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetLineSummaryController;
use Illuminate\Support\Facades\Route;

Route::get('production/lines/{line}', GetLineSummaryController::class)
    ->middleware(['auth:api'])
    ->whereIn('line', ['dtf1', 'dtf2', 'dtg']);
