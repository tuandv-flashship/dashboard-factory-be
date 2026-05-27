<?php

/**
 * @apiGroup  Order
 * @apiName   GetOrderSummaryHistory
 *
 * @api {GET} /v1/orders/summary/history Get Order Summary History
 *
 * @apiDescription Get order summary history by date range.
 * Supports optional line filter (dtf/dtg). Defaults to total across all lines.
 * Requires: dashboard.view permission.
 */

use App\Containers\AppSection\Order\UI\API\Controllers\GetOrderSummaryHistoryController;
use Illuminate\Support\Facades\Route;

Route::get('orders/summary/history', GetOrderSummaryHistoryController::class)
    ->middleware(['auth:api', 'throttle:60,1']);
