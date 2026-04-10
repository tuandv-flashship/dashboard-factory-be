<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   GetReasonCodes
 *
 * @api {GET} /v1/reason-codes Get Reason Codes
 *
 * @apiDescription Get all KPI miss reason codes, optionally filtered by line and department context.
 *
 * @apiQuery {String} [line]  Production line filter (e.g. dtf, dtg, pack_ship)
 * @apiQuery {String} [dept]  Department filter (e.g. print, cut, mockup, pack_ship, pick, pick_dtg)
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\GetReasonCodesController;
use Illuminate\Support\Facades\Route;

Route::get('reason-codes', GetReasonCodesController::class)
    ->middleware(['auth:api']);
