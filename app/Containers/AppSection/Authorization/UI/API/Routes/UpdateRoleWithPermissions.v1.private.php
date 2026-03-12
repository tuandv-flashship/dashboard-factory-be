<?php

/**
 * @apiGroup           RolePermission
 *
 * @apiName            UpdateRoleWithPermissions
 *
 * @api                {patch} /v1/roles/:role_id Update role and sync permissions
 *
 * @apiDescription     Update role display_name/description and sync permissions
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated ['permissions' => 'roles.edit', 'roles' => null]
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiParam           {String} role_id
 *
 * @apiBody            {String} [display_name]
 * @apiBody            {String} [description]
 * @apiBody            {Array} [permission_ids] Array of permission id's
 *
 * @apiUse             RoleSuccessSingleResponse
 */

use App\Containers\AppSection\Authorization\UI\API\Controllers\UpdateRoleWithPermissionsController;
use Illuminate\Support\Facades\Route;

Route::patch('roles/{role_id}', UpdateRoleWithPermissionsController::class)
    ->middleware(['auth:api']);
