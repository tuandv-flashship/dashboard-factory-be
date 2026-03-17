<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   DeleteReasonError
 *
 * @api {DELETE} /v1/admin/reason-errors/:id Delete Reason Error
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\DeleteReasonErrorController;
use Illuminate\Support\Facades\Route;

Route::delete('admin/reason-errors/{id}', DeleteReasonErrorController::class)
    ->middleware(['auth:api'])
    ->name('api_reason_code_delete_reason_error');
