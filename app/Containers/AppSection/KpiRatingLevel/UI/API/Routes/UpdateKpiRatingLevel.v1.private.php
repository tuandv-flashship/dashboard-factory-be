<?php

/**
 * @apiGroup  KpiRatingLevel
 * @apiName   UpdateKpiRatingLevel
 *
 * @api {PATCH} /v1/admin/kpi-rating-levels/:id Update KPI Rating Level
 */

use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\UpdateKpiRatingLevelController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/kpi-rating-levels/{id}', UpdateKpiRatingLevelController::class)
    ->middleware(['auth:api'])
    ->name('api_kpi_rating_level_update');
