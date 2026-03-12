<?php

/**
 * @apiGroup           Setting
 *
 * @apiName            GetPhoneNumberSettings
 *
 * @api                {get} /v1/settings/phone-number Get Phone Number Settings
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

use App\Containers\AppSection\Setting\UI\API\Controllers\GetPhoneNumberSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('settings/phone-number', GetPhoneNumberSettingsController::class)
    ->middleware(['auth:api']);
