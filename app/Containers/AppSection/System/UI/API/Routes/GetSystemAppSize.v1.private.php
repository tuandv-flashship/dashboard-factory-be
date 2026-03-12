<?php

/**
 * @apiGroup           System
 * @apiName            GetSystemAppSize
 * @api                {get} /v1/system/info/app-size Get System App Size
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 * @apiUse             SystemAppSizeResponse
 */

use App\Containers\AppSection\System\UI\API\Controllers\GetSystemAppSizeController;
use Illuminate\Support\Facades\Route;

Route::get('system/info/app-size', GetSystemAppSizeController::class)
    ->middleware(['auth:api']);
