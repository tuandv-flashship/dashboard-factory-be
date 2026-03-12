<?php

/**
 * @apiGroup           System
 * @apiName            ClearSystemCache
 * @api                {post} /v1/system/cache/clear Clear System Cache
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 * @apiBody            {String} type
 * @apiUse             SystemCacheActionResponse
 */

use App\Containers\AppSection\System\UI\API\Controllers\ClearSystemCacheController;
use Illuminate\Support\Facades\Route;

Route::post('system/cache/clear', ClearSystemCacheController::class)
    ->middleware(['auth:api']);
