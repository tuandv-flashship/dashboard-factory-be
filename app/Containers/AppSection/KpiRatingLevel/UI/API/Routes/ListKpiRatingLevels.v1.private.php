<?php

/**
 * @apiGroup  KpiRatingLevel
 * @apiName   ListKpiRatingLevels
 *
 * @api {GET} /v1/admin/kpi-rating-levels List KPI Rating Levels
 */

use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\ListKpiRatingLevelsController;
use Illuminate\Support\Facades\Route;

Route::get('admin/kpi-rating-levels', ListKpiRatingLevelsController::class)
    ->middleware(['auth:api'])
    ->name('api_kpi_rating_level_list');
