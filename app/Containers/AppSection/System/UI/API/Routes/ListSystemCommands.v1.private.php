<?php

/**
 * @apiGroup           System
 *
 * @apiName            ListSystemCommands
 *
 * @api                {get} /v1/system/commands List allowed system commands
 *
 * @apiDescription     List whitelisted system commands (admin only)
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated (Super Admin)
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiSuccessExample  {json} Success-Response:
 * {
 *   "data": [
 *     {
 *       "type": "SystemCommand",
 *       "id": "cache_clear",
 *       "action": "cache_clear",
 *       "command": "cache:clear",
 *       "options": []
 *     }
 *   ]
 * }
 */

use App\Containers\AppSection\System\UI\API\Controllers\ListSystemCommandsController;
use Illuminate\Support\Facades\Route;

Route::get('system/commands', ListSystemCommandsController::class)
    ->middleware(['auth:api']);
