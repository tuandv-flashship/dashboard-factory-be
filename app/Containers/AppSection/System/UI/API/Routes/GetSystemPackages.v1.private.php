<?php

/**
 * @apiGroup           System
 * @apiName            GetSystemPackages
 * @api                {get} /v1/system/info/packages Get System Packages
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 * @apiParam           {Number} [page]
 * @apiParam           {Number} [limit]
 * @apiUse             SystemPackagesResponse
 */

use App\Containers\AppSection\System\UI\API\Controllers\GetSystemPackagesController;
use Illuminate\Support\Facades\Route;

Route::get('system/info/packages', GetSystemPackagesController::class)
    ->middleware(['auth:api']);
