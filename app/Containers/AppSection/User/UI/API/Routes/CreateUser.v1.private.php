<?php

/**
 * @apiGroup           User
 *
 * @apiName            CreateUser
 *
 * @api                {post} /v1/users Create User
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated ['permissions' => 'users.create', 'roles' => null]
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiBody            {String} name min:2|max:50
 * @apiBody            {String} email
 * @apiBody            {String} password
 * @apiBody            {String} password_confirmation
 * @apiBody            {String="male","female","unspecified"} [gender]
 * @apiBody            {Date} [birth] format: Y-m-d / e.g. 2015-10-15
 * @apiBody            {String} [phone] max:20
 * @apiBody            {String} [description] max:500
 * @apiBody            {String="pending","active","inactive"} [status]
 * @apiBody            {Array} [role_ids] Array of hashed role IDs, assign after create
 *
 * @apiUse             UserSuccessSingleResponse
 */

use App\Containers\AppSection\User\UI\API\Controllers\CreateUserController;
use Illuminate\Support\Facades\Route;

Route::post('users', CreateUserController::class)
    ->middleware(['auth:api']);
