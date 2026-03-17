<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   ListReasonCategories
 *
 * @api {GET} /v1/admin/reason-categories List Reason Categories
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ListReasonCategoriesController;
use Illuminate\Support\Facades\Route;

Route::get('admin/reason-categories', ListReasonCategoriesController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_list_reason_categories');
