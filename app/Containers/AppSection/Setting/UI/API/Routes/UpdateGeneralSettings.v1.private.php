<?php

/**
 * @apiGroup           Setting
 *
 * @apiName            UpdateGeneralSettings
 *
 * @api                {patch} /v1/settings/general Update General Settings
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiBody            {String[]} [admin_email]
 * @apiBody            {String} [time_zone]
 * @apiBody            {Boolean} [enable_send_error_reporting_via_email]
 * @apiBody            {String} [locale]
 *
 * @apiUse             GeneralSettingsResponse
 */

use App\Containers\AppSection\Setting\UI\API\Controllers\UpdateGeneralSettingsController;
use Illuminate\Support\Facades\Route;

Route::patch('settings/general', UpdateGeneralSettingsController::class)
    ->middleware(['auth:api']);
