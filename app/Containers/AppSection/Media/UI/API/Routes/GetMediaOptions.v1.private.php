<?php

/**
 * @apiGroup           Media
 *
 * @apiName            GetMediaOptions
 *
 * @api                {get} /v1/media/options Get Media Options
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\Media\UI\API\Controllers\GetMediaOptionsController;
use Illuminate\Support\Facades\Route;

Route::get('media/options', GetMediaOptionsController::class)
    ->name('api_media_get_options')
    ->middleware(['auth:api']);
