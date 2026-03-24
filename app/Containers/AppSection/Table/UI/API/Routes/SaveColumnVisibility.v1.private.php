<?php

/**
 * @apiGroup           Table
 *
 * @apiName            SaveColumnVisibility
 *
 * @api                {put} /v1/table-columns-visibility Save Column Visibility
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiBody            {String} model Model key (e.g. 'post')
 * @apiBody            {Object} columns Column visibility map (e.g. {"id": true, "views": false})
 */

use App\Containers\AppSection\Table\UI\API\Controllers\ColumnVisibilityController;
use Illuminate\Support\Facades\Route;

Route::put('table-columns-visibility', ColumnVisibilityController::class)
    ->middleware(['auth:api']);
