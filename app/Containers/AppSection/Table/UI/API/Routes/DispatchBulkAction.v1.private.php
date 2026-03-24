<?php

/**
 * @apiGroup           Table
 *
 * @apiName            DispatchBulkAction
 *
 * @api                {post} /v1/bulk-actions Dispatch Bulk Action
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiBody            {String} model Model key (e.g. 'post')
 * @apiBody            {String} action Action key (e.g. 'delete')
 * @apiBody            {String[]} ids Array of hashed IDs
 */

use App\Containers\AppSection\Table\UI\API\Controllers\BulkActionController;
use Illuminate\Support\Facades\Route;

Route::post('bulk-actions', BulkActionController::class)
    ->middleware(['auth:api']);
