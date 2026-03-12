<?php

/**
 * @apiGroup  Order
 * @apiName   GetOrderSummaryPublic
 *
 * @api {GET} /v1/orders/summary Get Order Summary (Public)
 */

use App\Containers\AppSection\Order\UI\API\Controllers\GetOrderSummaryController;
use Illuminate\Support\Facades\Route;

Route::get('orders/summary', GetOrderSummaryController::class)
    ->middleware('throttle:60,1');
