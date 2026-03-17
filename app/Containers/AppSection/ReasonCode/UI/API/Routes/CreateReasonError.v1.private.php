<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   CreateReasonError
 *
 * @api {POST} /v1/admin/reason-errors Create Reason Error
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\CreateReasonErrorController;
use Illuminate\Support\Facades\Route;

Route::post('admin/reason-errors', CreateReasonErrorController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_create_reason_error');
