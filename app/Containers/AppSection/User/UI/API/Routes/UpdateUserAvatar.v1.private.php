<?php

/**
 * @apiGroup           User
 *
 * @apiName            UpdateUserAvatar
 *
 * @api                {post} /v1/users/:user_id/avatar Update User Avatar
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null] | Resource Owner
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiParam           {String} user_id
 *
 * @apiBody            {File} avatar Image file (max 5MB)
 *
 * @apiUse             UserSuccessSingleResponse
 */

use App\Containers\AppSection\User\UI\API\Controllers\UpdateUserAvatarController;
use Illuminate\Support\Facades\Route;

Route::post('users/{user_id}/avatar', UpdateUserAvatarController::class)
    ->middleware(['auth:api']);
