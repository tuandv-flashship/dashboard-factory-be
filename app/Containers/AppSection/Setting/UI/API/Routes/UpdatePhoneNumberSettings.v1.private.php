<?php

/**
 * @apiGroup           Setting
 *
 * @apiName            UpdatePhoneNumberSettings
 *
 * @api                {patch} /v1/settings/phone-number Update Phone Number Settings
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiUse             PhoneNumberSettingsResponse
 */

use App\Containers\AppSection\Setting\UI\API\Controllers\UpdatePhoneNumberSettingsController;
use Illuminate\Support\Facades\Route;

Route::patch('settings/phone-number', UpdatePhoneNumberSettingsController::class)
    ->middleware(['auth:api']);
