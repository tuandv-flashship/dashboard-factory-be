<?php

/**
 * @apiGroup  FplatformData
 * @apiName   GetLogFileCut
 *
 * @api {GET} /v1/fplatform/log-file-cut Get Log File Cut (log thời gian file CUT theo user)
 *
 * @apiParam {String} start_log  Start datetime (Y-m-d H:i:s)
 * @apiParam {String} end_log    End datetime (Y-m-d H:i:s)
 */

use App\Containers\AppSection\FplatformData\UI\API\Controllers\GetLogFileCutController;
use Illuminate\Support\Facades\Route;

Route::get('fplatform/log-file-cut', GetLogFileCutController::class);
