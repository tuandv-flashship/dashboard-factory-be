<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   ReorderReasonSubItems
 *
 * @api {PATCH} /v1/admin/reason-sub-items/reorder Reorder Reason Sub Items
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ReorderReasonSubItemsController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/reason-sub-items/reorder', ReorderReasonSubItemsController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_reorder_reason_sub_items');
