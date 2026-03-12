<?php

/**
 * @apiGroup           System
 *
 * @apiName            GetSystemCommandStatus
 *
 * @api                {get} /v1/system/commands/:job_id Get system command status
 *
 * @apiDescription     Get command execution status/result (admin only)
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated (Super Admin)
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiParam           {String} job_id
 *
 * @apiUse             SystemCommandResultResponse
 */

use App\Containers\AppSection\System\UI\API\Controllers\GetSystemCommandStatusController;
use Illuminate\Support\Facades\Route;

Route::get('system/commands/{job_id}', GetSystemCommandStatusController::class)
    ->middleware(['auth:api']);
