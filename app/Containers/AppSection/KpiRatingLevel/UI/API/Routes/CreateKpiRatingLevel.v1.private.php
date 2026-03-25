<?php

/**
 * @apiGroup  KpiRatingLevel
 * @apiName   CreateKpiRatingLevel
 *
 * @api {POST} /v1/admin/kpi-rating-levels Create KPI Rating Level
 */

use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\CreateKpiRatingLevelController;
use Illuminate\Support\Facades\Route;

Route::post('admin/kpi-rating-levels', CreateKpiRatingLevelController::class)
    ->middleware(['auth:api'])
    ->name('api_kpi_rating_level_create');
