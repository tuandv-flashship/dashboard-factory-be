<?php

/**
 * @apiGroup  FplatformData
 * @apiName   GetAllTeamsInventory
 *
 * @api {GET} /v1/admin/fplatform/inventory Get All Teams Inventory (tồn đầu/cuối tất cả bộ phận)
 *
 * @apiParam {String} [date] Date (Y-m-d), defaults to today
 */

use App\Containers\AppSection\FplatformData\UI\API\Controllers\GetAllTeamsInventoryController;
use Illuminate\Support\Facades\Route;

Route::get('admin/fplatform/inventory', GetAllTeamsInventoryController::class)
    ->middleware(['auth:api'])
    ->name('api_fplatform_inventory_all');

