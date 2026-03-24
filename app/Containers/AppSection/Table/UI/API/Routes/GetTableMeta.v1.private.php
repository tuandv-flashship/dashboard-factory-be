<?php

/**
 * @apiGroup           Table
 *
 * @apiName            GetTableMeta
 *
 * @api                {get} /v1/table-meta Get Table Metadata
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiQuery           {String} [model] Model key (e.g. 'post', 'category'). Omit to list all models.
 */

use App\Containers\AppSection\Table\UI\API\Controllers\TableMetaController;
use Illuminate\Support\Facades\Route;

Route::get('table-meta', TableMetaController::class)
    ->middleware(['auth:api']);
