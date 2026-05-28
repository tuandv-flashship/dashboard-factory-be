<?php

/**
 * @apiGroup  FplatformData
 * @apiName   GetOrderByEstimate
 *
 * @api {GET} /v1/fplatform/order-by-estimate Get Order by Estimate Date (tổng đơn theo ngày estimate)
 *
 * @apiParam {String} [date]   Date (Y-m-d), defaults to today
 */

use App\Containers\AppSection\FplatformData\UI\API\Controllers\GetOrderByEstimateController;
use Illuminate\Support\Facades\Route;

Route::get('fplatform/order-by-estimate', GetOrderByEstimateController::class)
    ->middleware(['auth:api', 'throttle:60,1']);
