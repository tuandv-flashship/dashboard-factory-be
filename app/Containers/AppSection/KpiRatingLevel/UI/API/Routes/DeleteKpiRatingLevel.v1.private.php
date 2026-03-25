<?php

/**
 * @apiGroup  KpiRatingLevel
 * @apiName   DeleteKpiRatingLevel
 *
 * @api {DELETE} /v1/admin/kpi-rating-levels/:id Delete KPI Rating Level
 */

use App\Containers\AppSection\KpiRatingLevel\UI\API\Controllers\DeleteKpiRatingLevelController;
use Illuminate\Support\Facades\Route;

Route::delete('admin/kpi-rating-levels/{id}', DeleteKpiRatingLevelController::class)
    ->middleware(['auth:api'])
    ->name('api_kpi_rating_level_delete');
