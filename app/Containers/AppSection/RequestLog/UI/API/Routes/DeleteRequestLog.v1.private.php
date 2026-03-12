<?php

/**
 * @apiGroup           RequestLog
 * @apiName            DeleteRequestLog
 * @api                {delete} /v1/request-logs/:request_log_id Delete Request Log
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\RequestLog\UI\API\Controllers\DeleteRequestLogController;
use Illuminate\Support\Facades\Route;

Route::delete('request-logs/{request_log_id}', DeleteRequestLogController::class)
    ->middleware(['auth:api']);
