<?php

/**
 * @apiGroup  Machine
 * @apiName   UpdateMachineStatus
 *
 * @api {PATCH} /v1/machines/:id/status Update Machine Status
 *
 * @apiDescription Update the operational status of a machine.
 *
 * @apiParam  {String} id Machine hashed ID
 * @apiBody   {String="online","offline","maintenance"} status New machine status
 */

use App\Containers\AppSection\Machine\UI\API\Controllers\UpdateMachineStatusController;
use Illuminate\Support\Facades\Route;

Route::patch('machines/{id}/status', UpdateMachineStatusController::class)
    ->middleware(['auth:api']);
