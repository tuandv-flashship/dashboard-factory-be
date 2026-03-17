<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   ListReasonSubItems
 *
 * @api {GET} /v1/admin/reason-sub-items List Reason Sub Items
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ListReasonSubItemsController;
use Illuminate\Support\Facades\Route;

Route::get('admin/reason-sub-items', ListReasonSubItemsController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_list_reason_sub_items');
