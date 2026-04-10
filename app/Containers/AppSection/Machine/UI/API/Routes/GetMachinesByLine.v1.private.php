<?php

/**
 * @apiGroup  Machine
 * @apiName   GetMachinesByLine
 *
 * @api {GET} /v1/machines/:line Get Machines By Line
 *
 * @apiDescription Get active machines filtered by production line.
 *
 * @apiParam {String} line Production line code (e.g. dtf, dtg, pack_ship)
 */

use App\Containers\AppSection\Machine\UI\API\Controllers\GetMachinesByLineController;
use Illuminate\Support\Facades\Route;

Route::get('machines/{line}', GetMachinesByLineController::class)
    ->middleware(['auth:api'])
    ->where('line', '[a-z_]+');
