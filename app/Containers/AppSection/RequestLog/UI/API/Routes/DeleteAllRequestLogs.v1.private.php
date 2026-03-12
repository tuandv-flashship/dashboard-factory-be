<?php

/**
 * @apiGroup           RequestLog
 * @apiName            DeleteAllRequestLogs
 * @api                {delete} /v1/request-logs/empty Delete All Request Logs
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\RequestLog\UI\API\Controllers\DeleteAllRequestLogsController;
use Illuminate\Support\Facades\Route;

Route::delete('request-logs/empty', DeleteAllRequestLogsController::class)
    ->middleware(['auth:api']);
