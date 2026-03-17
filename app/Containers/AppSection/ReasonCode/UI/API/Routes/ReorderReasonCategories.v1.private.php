<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   ReorderReasonCategories
 *
 * @api {PATCH} /v1/admin/reason-categories/reorder Reorder Reason Categories
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ReorderReasonCategoriesController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/reason-categories/reorder', ReorderReasonCategoriesController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_reorder_reason_categories');
