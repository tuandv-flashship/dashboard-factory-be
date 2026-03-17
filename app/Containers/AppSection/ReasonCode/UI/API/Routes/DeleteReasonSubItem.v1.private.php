<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   DeleteReasonSubItem
 *
 * @api {DELETE} /v1/admin/reason-sub-items/:id Delete Reason Sub Item
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\DeleteReasonSubItemController;
use Illuminate\Support\Facades\Route;

Route::delete('admin/reason-sub-items/{id}', DeleteReasonSubItemController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_delete_reason_sub_item');
