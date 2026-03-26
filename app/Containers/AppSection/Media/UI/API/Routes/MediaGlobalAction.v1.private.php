<?php

/**
 * @apiGroup           Media
 *
 * @apiName            MediaGlobalAction
 *
 * @api                {post} /v1/media/actions Media Global Actions
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\Media\UI\API\Controllers\MediaGlobalActionController;
use Illuminate\Support\Facades\Route;

Route::post('media/actions', MediaGlobalActionController::class)
    ->name('api_media_global_action')
    ->middleware(['auth:api']);
