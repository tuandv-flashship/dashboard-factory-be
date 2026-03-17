<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   UpdateReasonSubItem
 *
 * @api {PATCH} /v1/admin/reason-sub-items/:id Update Reason Sub Item
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\UpdateReasonSubItemController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/reason-sub-items/{id}', UpdateReasonSubItemController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_update_reason_sub_item');
