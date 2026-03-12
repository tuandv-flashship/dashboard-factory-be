<?php

/**
 * @apiGroup           System
 *
 * @apiName            RunSystemCommand
 *
 * @api                {post} /v1/system/commands Run system command
 *
 * @apiDescription     Run a whitelisted system command (admin only)
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated (Super Admin)
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiBody            {String="cache_clear","config_cache","migrate","queue_restart","queue_work_once","permissions_sync"} action
 *
 * @apiUse             SystemCommandResultResponse
 *
 * @apiSuccessExample  {json} Success-Response:
 * {
 *   "data": {
 *     "type": "SystemCommandResult",
 *     "id": "b86c43ad-0c91-4a3a-95c5-2a7b5f3e8a33",
 *     "job_id": "b86c43ad-0c91-4a3a-95c5-2a7b5f3e8a33",
 *     "action": "cache_clear",
 *     "command": "cache:clear",
 *     "status": "queued",
 *     "exit_code": null,
 *     "output": null
 *   }
 * }
 */

use App\Containers\AppSection\System\UI\API\Controllers\RunSystemCommandController;
use Illuminate\Support\Facades\Route;

Route::post('system/commands', RunSystemCommandController::class)
    ->middleware(['auth:api']);
