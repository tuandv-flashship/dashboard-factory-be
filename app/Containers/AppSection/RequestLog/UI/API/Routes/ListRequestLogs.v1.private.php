<?php

/**
 * @apiGroup           RequestLog
 * @apiName            ListRequestLogs
 * @api                {get} /v1/request-logs List Request Logs
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 * @apiParam           {Number} [page]
 * @apiParam           {Number} [limit]
 * @apiUse             RequestLogResponse
 */

use App\Containers\AppSection\RequestLog\UI\API\Controllers\ListRequestLogsController;
use Illuminate\Support\Facades\Route;

Route::get('request-logs', ListRequestLogsController::class)
    ->middleware(['auth:api']);
