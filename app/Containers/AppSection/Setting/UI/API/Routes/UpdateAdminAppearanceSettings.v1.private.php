<?php

/**
 * @apiGroup           Setting
 *
 * @apiName            UpdateAdminAppearanceSettings
 *
 * @api                {patch} /v1/settings/admin-appearance Update Admin Appearance Settings
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

use App\Containers\AppSection\Setting\UI\API\Controllers\UpdateAdminAppearanceSettingsController;
use Illuminate\Support\Facades\Route;

Route::patch('settings/admin-appearance', UpdateAdminAppearanceSettingsController::class)
    ->middleware(['auth:api']);
