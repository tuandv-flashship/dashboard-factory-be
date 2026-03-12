<?php

/**
 * @apiGroup           Authentication
 *
 * @apiName            RegisterUser
 *
 * @api                {post} /v1/register Register User
 *
 * @apiDescription     Register a new user. New self-registered users are created with
 *                     default status `pending` and require admin activation.
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      none
 *
 * @apiHeader          {String} accept=application/json
 *
 * @apiBody            {String} email
 * @apiBody            {String} password min: 8
 *
 * at least one character of the following:
 *
 * upper case letter
 *
 * lower case letter
 *
 * number
 *
 * special character
 *
 * @apiParam           {String} [name] min:2|max:50
 * @apiParam           {String="male","female","unspecified"} [gender]
 * @apiParam           {Date} [birth] format: Y-m-d / e.g. 2015-10-15
 * @apiParam           {String} [phone] max:20
 * @apiParam           {String} [description] max:500
 *
 * @apiUse             UserSuccessSingleResponse
 */

use App\Containers\AppSection\Authentication\UI\API\Controllers\RegisterUserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterUserController::class)
    ->middleware(['throttle:' . config('appSection-authentication.throttle.register', '6,1')])
    ->name('api_auth_register_user');
