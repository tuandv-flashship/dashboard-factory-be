<?php

/**
 * @apiGroup  Machine
 * @apiName   GetMachinesByLinePublic
 *
 * @api {GET} /v1/machines/:line Get Machines By Line (Public)
 */

use App\Containers\AppSection\Machine\UI\API\Controllers\GetMachinesByLineController;
use Illuminate\Support\Facades\Route;

Route::get('machines/{line}', GetMachinesByLineController::class)
    ->where('line', 'dtf1|dtf2|dtg')
    ->middleware('throttle:60,1');
