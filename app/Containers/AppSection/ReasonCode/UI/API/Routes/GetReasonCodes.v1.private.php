<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   GetReasonCodes
 *
 * @api {GET} /v1/reason-codes Get Reason Codes
 *
 * @apiDescription Get all KPI miss reason codes, optionally filtered by line and department context.
 *
 * @apiQuery {String} [line]  Production line filter (dtf1, dtf2, dtg)
 * @apiQuery {String} [dept]  Department filter (print, cut, mockup, pack_ship, pick, dtg_print)
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\GetReasonCodesController;
use Illuminate\Support\Facades\Route;

Route::get('reason-codes', GetReasonCodesController::class)
    ->middleware(['auth:api']);
