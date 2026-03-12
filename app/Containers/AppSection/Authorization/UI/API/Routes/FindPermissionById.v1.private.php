<?php

/**
 * @apiGroup           RolePermission
 *
 * @apiName            FindPermissionById
 *
 * @api                {get} /v1/permissions/:permission_id Find a permission by id
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated ['permissions' => 'manage-roles', 'roles' => null]
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiParam           {String} permission_id
 *
 * @apiUse             PermissionSuccessSingleResponse
 */

use App\Containers\AppSection\Authorization\UI\API\Controllers\FindPermissionByIdController;
use Illuminate\Support\Facades\Route;

$minLength = (int) config('hashids.connections.main.length', 16);

Route::get('permissions/{permission_id}', FindPermissionByIdController::class)
    ->where('permission_id', '[A-Za-z0-9]{' . $minLength . ',}')
    ->middleware(['auth:api']);
