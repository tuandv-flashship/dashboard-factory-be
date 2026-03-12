<?php

/**
 * @apiGroup           Icon
 *
 * @apiName            ListIcons
 *
 * @api                {get} /v1/icons List Icons
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\Icon\UI\API\Controllers\ListIconsController;
use Illuminate\Support\Facades\Route;

Route::get('icons', ListIconsController::class)
    ->middleware(['auth:api']);
