<?php

/**
 * @apiGroup           Table
 *
 * @apiName            DispatchBulkChange
 *
 * @api                {post} /v1/bulk-changes Dispatch Bulk Change
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiBody            {String} model Model key (e.g. 'post')
 * @apiBody            {String[]} ids Array of hashed IDs
 * @apiBody            {String} key Field key (e.g. 'status')
 * @apiBody            {String} value New value (e.g. 'published')
 */

use App\Containers\AppSection\Table\UI\API\Controllers\BulkChangeController;
use Illuminate\Support\Facades\Route;

Route::post('bulk-changes', BulkChangeController::class)
    ->middleware(['auth:api']);
