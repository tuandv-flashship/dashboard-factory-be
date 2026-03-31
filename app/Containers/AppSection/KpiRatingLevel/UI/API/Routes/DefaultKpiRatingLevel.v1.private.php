<?php

/**
 * @apiGroup  KpiRatingLevel
 * @apiName   GetDefaultKpiRatingLevel
 *
 * @api {GET} /v1/admin/kpi-rating-levels/default Get Default KPI Rating Level
 */

use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\GetDefaultKpiRatingLevelController;
use Illuminate\Support\Facades\Route;

Route::get('admin/kpi-rating-levels/default', GetDefaultKpiRatingLevelController::class)
    ->middleware(['auth:api'])
    ->name('api_kpi_rating_level_get_default');
