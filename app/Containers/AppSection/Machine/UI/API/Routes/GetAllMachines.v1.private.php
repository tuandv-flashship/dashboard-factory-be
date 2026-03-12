<?php

/**
 * @apiGroup  Machine
 * @apiName   GetAllMachines
 *
 * @api {GET} /v1/machines Get All Machines
 *
 * @apiDescription Get all active machines with their current status.
 */

use App\Containers\AppSection\Machine\UI\API\Controllers\GetAllMachinesController;
use Illuminate\Support\Facades\Route;

Route::get('machines', GetAllMachinesController::class)
    ->middleware(['auth:api']);
