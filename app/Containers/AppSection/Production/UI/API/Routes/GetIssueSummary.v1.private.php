<?php

/**
 * @apiGroup  Production
 * @apiName   GetIssueSummary
 *
 * @api {GET} /v1/admin/production/issues/summary Get Issue Summary
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetIssueSummaryController;
use Illuminate\Support\Facades\Route;

Route::get('admin/production/issues/summary', GetIssueSummaryController::class)
    ->middleware(['auth:api'])
    ->name('api_production_get_issue_summary');
