<?php

/**
 * @apiGroup  KpiRatingLevel
 * @apiName   FindKpiRatingLevel
 *
 * @api {GET} /v1/admin/kpi-rating-levels/:id Find KPI Rating Level
 */

use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\FindKpiRatingLevelController;
use Illuminate\Support\Facades\Route;

Route::get('admin/kpi-rating-levels/{id}', FindKpiRatingLevelController::class)
    ->middleware(['auth:api'])
    ->name('api_kpi_rating_level_find');
