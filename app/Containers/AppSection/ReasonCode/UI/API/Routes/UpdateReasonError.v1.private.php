<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   UpdateReasonError
 *
 * @api {PATCH} /v1/admin/reason-errors/:id Update Reason Error
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\UpdateReasonErrorController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/reason-errors/{id}', UpdateReasonErrorController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_update_reason_error');
