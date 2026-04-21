<?php

/**
 * @apiGroup  Production
 * @apiName   UnresolveHourlyIssue
 *
 * @api {PATCH} /v1/admin/hourly-issues/:id/unresolve Undo Issue Resolution
 */

use App\Containers\AppSection\Production\UI\API\Controllers\UnresolveHourlyIssueController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/hourly-issues/{id}/unresolve', UnresolveHourlyIssueController::class)
    ->middleware(['auth:api'])
    ->name('api_production_unresolve_hourly_issue');
