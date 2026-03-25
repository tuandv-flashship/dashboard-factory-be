<?php

/**
 * @apiGroup           System
 *
 * @apiName            RunDevArtisanCommand
 *
 * @api                {post} /v1/dev/artisan Run any artisan command (dev only)
 *
 * @apiDescription     Run any artisan command synchronously. Only available in local/development/staging environments.
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated (Super Admin, non-production only)
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiBody            {String} command  Artisan command name (e.g. "translations:import", "cache:clear")
 * @apiBody            {Object} [options]  Key-value options (e.g. {"--fresh": true, "--locale": "vi"})
 *
 * @apiSuccessExample  {json} Success-Response:
 * {
 *   "data": {
 *     "command": "translations:import",
 *     "options": {"--fresh": true},
 *     "exit_code": 0,
 *     "output": "✅ Done. Imported: 1234, Skipped: 0",
 *     "success": true
 *   }
 * }
 */

use App\Containers\AppSection\System\UI\API\Controllers\RunDevArtisanCommandController;
use Illuminate\Support\Facades\Route;

Route::post('dev/artisan', RunDevArtisanCommandController::class)
    ->middleware(['auth:api']);
