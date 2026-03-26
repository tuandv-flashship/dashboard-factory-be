<?php

/**
 * @apiGroup           Media
 *
 * @apiName            ListMediaFolderTree
 *
 * @api                {get} /v1/media/folders/tree List Media Folder Tree
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\Media\UI\API\Controllers\ListMediaFolderTreeController;
use Illuminate\Support\Facades\Route;

Route::get('media/folders/tree', ListMediaFolderTreeController::class)
    ->name('api_media_list_media_folder_tree')
    ->middleware(['auth:api']);
