<?php

/**
 * @apiGroup           Media
 *
 * @apiName            ListMedia
 *
 * @api                {get} /v1/media/list List Media
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\Media\UI\API\Controllers\ListMediaController;
use Illuminate\Support\Facades\Route;

Route::get('media/list', ListMediaController::class)
    ->name('api_media_list_media')
    ->middleware(['auth:api']);
