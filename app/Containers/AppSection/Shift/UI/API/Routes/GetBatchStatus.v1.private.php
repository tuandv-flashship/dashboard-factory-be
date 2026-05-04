<?php

/**
 * @apiGroup  Batch
 * @apiName   GetBatchStatus
 *
 * @api {GET} /v1/admin/batches/:batch_id Get Batch Status
 */

use App\Containers\AppSection\Shift\UI\API\Controllers\GetBatchStatusController;
use Illuminate\Support\Facades\Route;

Route::get('admin/batches/{batch_id}', GetBatchStatusController::class)
    ->middleware(['auth:api'])
    ->name('api_batch_status');
