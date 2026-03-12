<?php

/**
 * @apiGroup           RequestLog
 * @apiName            GetRequestLogWidget
 * @api                {get} /v1/request-logs/widgets/request-errors Request Log Widget
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 * @apiParam           {Number} [page]
 * @apiParam           {Number} [limit]
 * @apiUse             RequestLogResponse
 */

use App\Containers\AppSection\RequestLog\UI\API\Controllers\GetRequestLogWidgetController;
use Illuminate\Support\Facades\Route;

Route::get('request-logs/widgets/request-errors', GetRequestLogWidgetController::class)
    ->middleware(['auth:api']);
