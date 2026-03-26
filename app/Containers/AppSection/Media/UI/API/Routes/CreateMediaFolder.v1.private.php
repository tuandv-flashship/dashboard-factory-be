<?php

/**
 * @apiGroup           Media
 *
 * @apiName            CreateMediaFolder
 *
 * @api                {post} /v1/media/folders Create Media Folder
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\Media\UI\API\Controllers\CreateMediaFolderController;
use Illuminate\Support\Facades\Route;

Route::post('media/folders', CreateMediaFolderController::class)
    ->name('api_media_create_media_folder')
    ->middleware(['auth:api']);
