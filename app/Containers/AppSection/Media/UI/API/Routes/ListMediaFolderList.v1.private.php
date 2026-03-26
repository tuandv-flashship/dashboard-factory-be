<?php

/**
 * @apiGroup           Media
 *
 * @apiName            ListMediaFolderList
 *
 * @api                {get} /v1/media/folders/list List Media Folders
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\Media\UI\API\Controllers\ListMediaFolderListController;
use Illuminate\Support\Facades\Route;

Route::get('media/folders/list', ListMediaFolderListController::class)
    ->name('api_media_list_media_folder_list')
    ->middleware(['auth:api']);
