<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   ListReasonErrors
 *
 * @api {GET} /v1/admin/reason-errors List Reason Errors
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ListReasonErrorsController;
use Illuminate\Support\Facades\Route;

Route::get('admin/reason-errors', ListReasonErrorsController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_list_reason_errors');
