<?php

/**
 * @apiGroup           Setting
 *
 * @apiName            GetAdminAppearanceSettings
 *
 * @api                {get} /v1/settings/admin-appearance Get Admin Appearance Settings
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiUse             AdminAppearanceSettingsResponse
 */

use App\Containers\AppSection\Setting\UI\API\Controllers\GetAdminAppearanceSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('settings/admin-appearance', GetAdminAppearanceSettingsController::class)
    ->middleware(['auth:api']);
