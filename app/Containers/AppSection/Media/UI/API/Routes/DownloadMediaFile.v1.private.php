<?php

/**
 * @apiGroup           Media
 *
 * @apiName            DownloadMediaFile
 *
 * @api                {post} /v1/media/files/download-url Download Media File by URL
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\Media\UI\API\Controllers\DownloadMediaFileController;
use Illuminate\Support\Facades\Route;

Route::post('media/files/download-url', DownloadMediaFileController::class)
    ->name('api_media_download_media_file')
    ->middleware(['auth:api']);
