<?php

/**
 * @apiGroup  Production
 * @apiName   DeleteHourlyIssue
 *
 * @api {DELETE} /v1/admin/hourly-issues/:id Delete Hourly Issue
 */

use App\Containers\AppSection\Production\UI\API\Controllers\DeleteHourlyIssueController;
use Illuminate\Support\Facades\Route;

Route::delete('admin/hourly-issues/{id}', DeleteHourlyIssueController::class)
    ->middleware(['auth:api'])
    ->name('api_production_delete_hourly_issue');
