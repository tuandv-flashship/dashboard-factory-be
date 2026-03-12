<?php

/**
 * @apiGroup  Order
 * @apiName   GetOrderSummary
 *
 * @api {GET} /v1/orders/summary Get Order Summary
 *
 * @apiDescription Get today's order summary (total + per production line).
 */

use App\Containers\AppSection\Order\UI\API\Controllers\GetOrderSummaryController;
use Illuminate\Support\Facades\Route;

Route::get('orders/summary', GetOrderSummaryController::class)
    ->middleware(['auth:api']);
