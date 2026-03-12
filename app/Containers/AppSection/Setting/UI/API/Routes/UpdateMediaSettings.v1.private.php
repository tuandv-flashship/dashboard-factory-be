<?php

/**
 * @apiGroup           Settings
 *
 * @apiName            UpdateMediaSettings
 *
 * @api                {patch} /v1/settings/media Update Media Settings
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\Setting\UI\API\Controllers\UpdateMediaSettingsController;
use Illuminate\Support\Facades\Route;

Route::patch('settings/media', UpdateMediaSettingsController::class)
    ->middleware(['auth:api']);
