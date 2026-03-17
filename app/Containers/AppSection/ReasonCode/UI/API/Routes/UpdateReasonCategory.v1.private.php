<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   UpdateReasonCategory
 *
 * @api {PATCH} /v1/admin/reason-categories/:id Update Reason Category
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\UpdateReasonCategoryController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/reason-categories/{id}', UpdateReasonCategoryController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_update_reason_category');
