<?php

/**
 * @apiGroup  Production
 * @apiName   ListHourlyIssues
 *
 * @api {GET} /v1/admin/production/issues List Hourly Issues
 */

use App\Containers\AppSection\Production\UI\API\Controllers\ListHourlyIssuesController;
use Illuminate\Support\Facades\Route;

Route::get('admin/production/issues', ListHourlyIssuesController::class)
    ->middleware(['auth:api'])
    ->name('api_production_list_hourly_issues');
