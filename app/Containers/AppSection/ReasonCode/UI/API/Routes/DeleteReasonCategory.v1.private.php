<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   DeleteReasonCategory
 *
 * @api {DELETE} /v1/admin/reason-categories/:id Delete Reason Category
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\DeleteReasonCategoryController;
use Illuminate\Support\Facades\Route;

Route::delete('admin/reason-categories/{id}', DeleteReasonCategoryController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_delete_reason_category');
