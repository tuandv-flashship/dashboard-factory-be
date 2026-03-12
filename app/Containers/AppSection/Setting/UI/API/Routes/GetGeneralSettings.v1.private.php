<?php

/**
 * @apiGroup           Setting
 *
 * @apiName            GetGeneralSettings
 *
 * @api                {get} /v1/settings/general Get General Settings
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiUse             GeneralSettingsResponse
 */

use App\Containers\AppSection\Setting\UI\API\Controllers\GetGeneralSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('settings/general', GetGeneralSettingsController::class)
    ->middleware(['auth:api']);
