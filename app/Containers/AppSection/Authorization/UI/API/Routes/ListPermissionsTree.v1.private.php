<?php

/**
 * @apiGroup           RolePermission
 *
 * @apiName            ListPermissionsTree
 *
 * @api                {get} /v1/permissions/tree List permissions as a nested tree
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated ['permissions' => 'manage-roles', 'roles' => null]
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiParam           {String} guard (optional) Guard name to filter permissions
 */

use App\Containers\AppSection\Authorization\UI\API\Controllers\ListPermissionsTreeController;
use Illuminate\Support\Facades\Route;

Route::get('permissions/tree', ListPermissionsTreeController::class)
    ->middleware(['auth:api']);
