<?php

/**
 * @apiGroup  KpiRatingLevel
 * @apiName   GetActiveKpiRatingLevel
 *
 * @api {GET} /v1/kpi-rating-levels/active Get Active KPI Rating Level (Public)
 */

use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\GetActiveKpiRatingLevelController;
use Illuminate\Support\Facades\Route;

Route::get('kpi-rating-levels/active', GetActiveKpiRatingLevelController::class)
    ->middleware('throttle:60,1');
