<?php

/**
 * @apiGroup  Alert
 * @apiName   GetAlerts
 *
 * @api {GET} /v1/alerts Get Alerts
 *
 * @apiDescription Get current unresolved alerts, optionally filtered by production line.
 *
 * @apiQuery {String} [line] Production line filter (dtf1, dtf2, dtg). Also includes line="all" alerts.
 */

use App\Containers\AppSection\Alert\UI\API\Controllers\GetAlertsController;
use Illuminate\Support\Facades\Route;

Route::get('alerts', GetAlertsController::class)
    ->middleware(['auth:api']);
