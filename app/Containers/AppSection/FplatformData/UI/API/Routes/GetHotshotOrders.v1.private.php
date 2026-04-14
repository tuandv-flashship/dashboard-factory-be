<?php

/**
 * @apiGroup  FplatformData
 * @apiName   GetHotshotOrders
 *
 * @api {GET} /v1/admin/fplatform/hotshot-orders Get Hotshot Order Inventory (tồn đơn hotshot)
 *
 * @apiParam {String} [date] Date (Y-m-d), defaults to today
 */

use App\Containers\AppSection\FplatformData\UI\API\Controllers\GetHotshotOrdersController;
use Illuminate\Support\Facades\Route;

Route::get('admin/fplatform/hotshot-orders', GetHotshotOrdersController::class)
    ->middleware(['auth:api'])
    ->name('api_fplatform_hotshot_orders');
