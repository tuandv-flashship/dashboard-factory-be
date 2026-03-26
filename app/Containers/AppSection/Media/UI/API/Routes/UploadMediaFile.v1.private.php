<?php

/**
 * @apiGroup           Media
 *
 * @apiName            UploadMediaFile
 *
 * @api                {post} /v1/media/files/upload Upload Media File
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\Media\UI\API\Controllers\UploadMediaFileController;
use Illuminate\Support\Facades\Route;

Route::post('media/files/upload', UploadMediaFileController::class)
    ->name('api_media_upload_media_file')
    ->middleware(['auth:api']);
