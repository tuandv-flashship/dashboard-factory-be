<?php

/**
 * @apiGroup  FplatformData
 * @apiName   GetHourlyMetrics
 *
 * @api {GET} /v1/admin/fplatform/hourly-metrics Get Hourly Metrics (hiệu suất/nhân viên theo giờ)
 *
 * @apiParam {String} team       Team: in, cat, pick, mockup, pack_ship, dtg_print, dtg_pick
 * @apiParam {String} metric     Metric type: productivity, staff_count, staff_productivity, machine_productivity
 * @apiParam {String} start_shift Shift start datetime (US/Central)
 * @apiParam {String} end_shift   Shift end datetime (US/Central)
 */

use App\Containers\AppSection\FplatformData\UI\API\Controllers\GetHourlyMetricsController;
use Illuminate\Support\Facades\Route;

Route::get('admin/fplatform/hourly-metrics', GetHourlyMetricsController::class)
    ->middleware(['auth:api'])
    ->name('api_fplatform_hourly_metrics');
