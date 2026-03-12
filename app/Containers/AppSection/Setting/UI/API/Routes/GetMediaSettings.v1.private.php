<?php

/**
 * @apiGroup           Settings
 *
 * @apiName            GetMediaSettings
 *
 * @api                {get} /v1/settings/media Get Media Settings
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\Setting\UI\API\Controllers\GetMediaSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('settings/media', GetMediaSettingsController::class)
    ->middleware(['auth:api']);
