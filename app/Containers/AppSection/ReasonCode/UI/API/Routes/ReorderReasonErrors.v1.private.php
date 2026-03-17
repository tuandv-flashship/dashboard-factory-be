<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   ReorderReasonErrors
 *
 * @api {PATCH} /v1/admin/reason-errors/reorder Reorder Reason Errors
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\ReorderReasonErrorsController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/reason-errors/reorder', ReorderReasonErrorsController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_reorder_reason_errors');
