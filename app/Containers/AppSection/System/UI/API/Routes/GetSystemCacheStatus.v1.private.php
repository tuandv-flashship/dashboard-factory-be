<?php

/**
 * @apiGroup           System
 * @apiName            GetSystemCacheStatus
 * @api                {get} /v1/system/cache Get System Cache Status
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 * @apiUse             SystemCacheStatusResponse
 */

use App\Containers\AppSection\System\UI\API\Controllers\GetSystemCacheStatusController;
use Illuminate\Support\Facades\Route;

Route::get('system/cache', GetSystemCacheStatusController::class)
    ->middleware(['auth:api']);
