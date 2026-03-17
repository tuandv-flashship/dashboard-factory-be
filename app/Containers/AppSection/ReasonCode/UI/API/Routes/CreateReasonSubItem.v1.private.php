<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   CreateReasonSubItem
 *
 * @api {POST} /v1/admin/reason-sub-items Create Reason Sub Item
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\CreateReasonSubItemController;
use Illuminate\Support\Facades\Route;

Route::post('admin/reason-sub-items', CreateReasonSubItemController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_create_reason_sub_item');
