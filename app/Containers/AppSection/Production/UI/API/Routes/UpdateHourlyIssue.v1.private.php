<?php

/**
 * @apiGroup  Production
 * @apiName   UpdateHourlyIssue
 *
 * @api {PATCH} /v1/admin/hourly-issues/:id Update Hourly Issue
 */

use App\Containers\AppSection\Production\UI\API\Controllers\UpdateHourlyIssueController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/hourly-issues/{id}', UpdateHourlyIssueController::class)
    ->middleware(['auth:api'])
    ->name('api_production_update_hourly_issue');
