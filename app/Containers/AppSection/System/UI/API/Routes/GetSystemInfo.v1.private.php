<?php

/**
 * @apiGroup           System
 * @apiName            GetSystemInfo
 * @api                {get} /v1/system/info Get System Info
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 * @apiUse             SystemInfoResponse
 */

use App\Containers\AppSection\System\UI\API\Controllers\GetSystemInfoController;
use Illuminate\Support\Facades\Route;

Route::get('system/info', GetSystemInfoController::class)
    ->middleware(['auth:api']);
