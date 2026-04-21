<?php

/**
 * @apiGroup  Production
 * @apiName   ResolveHourlyIssue
 *
 * @api {PATCH} /v1/admin/hourly-issues/:id/resolve Mark Issue as Resolved
 */

use App\Containers\AppSection\Production\UI\API\Controllers\ResolveHourlyIssueController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/hourly-issues/{id}/resolve', ResolveHourlyIssueController::class)
    ->middleware(['auth:api'])
    ->name('api_production_resolve_hourly_issue');
