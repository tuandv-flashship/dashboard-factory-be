<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   CreateReasonCategory
 *
 * @api {POST} /v1/admin/reason-categories Create Reason Category
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\CreateReasonCategoryController;
use Illuminate\Support\Facades\Route;

Route::post('admin/reason-categories', CreateReasonCategoryController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_create_reason_category');
