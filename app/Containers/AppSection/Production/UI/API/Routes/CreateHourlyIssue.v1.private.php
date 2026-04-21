<?php

/**
 * @apiGroup  Production
 * @apiName   CreateHourlyIssue
 *
 * @api {POST} /v1/admin/hourly-records/:id/issues Create Hourly Issue
 */

use App\Containers\AppSection\Production\UI\API\Controllers\CreateHourlyIssueController;
use Illuminate\Support\Facades\Route;

Route::post('admin/hourly-records/{id}/issues', CreateHourlyIssueController::class)
    ->middleware(['auth:api'])
    ->name('api_production_create_hourly_issue');
