<?php

/**
 * @apiGroup  Production
 * @apiName   GetPendingIssues
 *
 * @api {GET} /v1/admin/production/pending-issues Get Pending Issues
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetPendingIssuesController;
use Illuminate\Support\Facades\Route;

Route::get('admin/production/pending-issues', GetPendingIssuesController::class)
    ->middleware(['auth:api'])
    ->name('api_production_get_pending_issues');
