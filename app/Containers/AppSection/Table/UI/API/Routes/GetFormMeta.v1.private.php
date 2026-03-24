<?php

/**
 * @apiGroup           Table
 *
 * @apiName            GetFormMeta
 *
 * @api                {get} /v1/form-meta Get Form Metadata
 *
 * @apiVersion         1.0.0
 *
 * @apiPermission      Authenticated (permission checked per action)
 *
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 *
 * @apiQuery           {String} model  Model key (e.g. 'department')
 * @apiQuery           {String} action Form action (e.g. 'create', 'update')
 */

use App\Containers\AppSection\Table\UI\API\Controllers\FormMetaController;
use Illuminate\Support\Facades\Route;

Route::get('form-meta', FormMetaController::class)
    ->middleware(['auth:api']);
