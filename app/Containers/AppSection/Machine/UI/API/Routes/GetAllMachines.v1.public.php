<?php

/**
 * @apiGroup  Machine
 * @apiName   GetAllMachinesPublic
 *
 * @api {GET} /v1/machines Get All Machines (Public)
 *
 * @apiDescription Public endpoint for TV dashboard — no auth required.
 */

use App\Containers\AppSection\Machine\UI\API\Controllers\GetAllMachinesController;
use Illuminate\Support\Facades\Route;

Route::get('machines', GetAllMachinesController::class)
    ->middleware('throttle:60,1');
