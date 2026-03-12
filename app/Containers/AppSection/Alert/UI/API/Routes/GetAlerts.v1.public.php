<?php

/**
 * @apiGroup  Alert
 * @apiName   GetAlertsPublic
 *
 * @api {GET} /v1/alerts Get Alerts (Public)
 */

use App\Containers\AppSection\Alert\UI\API\Controllers\GetAlertsController;
use Illuminate\Support\Facades\Route;

Route::get('alerts', GetAlertsController::class)
    ->middleware('throttle:60,1');
