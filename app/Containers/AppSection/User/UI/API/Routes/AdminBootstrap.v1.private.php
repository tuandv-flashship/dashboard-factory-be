<?php

/**
 * @apiGroup           User
 *
 * @apiName            AdminBootstrap
 *
 * @api                {get} /v1/admin/bootstrap Admin Bootstrap
 *
 * @apiDescription     Get bootstrap data for the admin panel including user info,
 *                     permissions, roles, and available locales.
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 */

use App\Containers\AppSection\User\UI\API\Controllers\AdminBootstrapController;
use Illuminate\Support\Facades\Route;

Route::get('admin/bootstrap', AdminBootstrapController::class)
    ->middleware(['auth:api']);
