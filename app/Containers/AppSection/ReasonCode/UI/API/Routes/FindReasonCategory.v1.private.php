<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   FindReasonCategory
 *
 * @api {GET} /v1/admin/reason-categories/:id Find Reason Category
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\FindReasonCategoryController;
use Illuminate\Support\Facades\Route;

Route::get('admin/reason-categories/{id}', FindReasonCategoryController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_find_reason_category');
