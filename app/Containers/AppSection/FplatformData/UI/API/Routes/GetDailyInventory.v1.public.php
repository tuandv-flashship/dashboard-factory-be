<?php

/**
 * @apiGroup  FplatformData
 * @apiName   GetDailyInventory
 *
 * @api {GET} /v1/fplatform/daily-inventory Get Daily Inventory (tồn đầu/cuối ngày)
 *
 * @apiParam {String} factory  Factory line: FLS or PD
 * @apiParam {String} [date]   Date (Y-m-d), defaults to today
 * @apiParam {String} [work_type] Work type: in (default), cat, pick
 */

use App\Containers\AppSection\FplatformData\UI\API\Controllers\GetDailyInventoryController;
use Illuminate\Support\Facades\Route;

Route::get('fplatform/daily-inventory', GetDailyInventoryController::class)
    ->middleware('throttle:60,1');
