<?php

/**
 * @apiGroup  ReasonCode
 * @apiName   GetReasonCodesPublic
 *
 * @api {GET} /v1/reason-codes Get Reason Codes (Public)
 */

use App\Containers\AppSection\ReasonCode\UI\API\Controllers\GetReasonCodesController;
use Illuminate\Support\Facades\Route;

Route::get('reason-codes', GetReasonCodesController::class)
    ->middleware('throttle:60,1');
