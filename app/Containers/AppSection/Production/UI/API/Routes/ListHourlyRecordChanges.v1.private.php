<?php

/**
 * @apiGroup  Production
 * @apiName   ListHourlyRecordChanges
 *
 * @api {GET} /v1/admin/production/hourly-records/:hourly_record_id/changes List Hourly Record Changes
 */

use App\Containers\AppSection\Production\UI\API\Controllers\ListHourlyRecordChangesController;
use Illuminate\Support\Facades\Route;

Route::get('admin/production/hourly-records/{hourly_record_id}/changes', ListHourlyRecordChangesController::class)
    ->middleware(['auth:api'])
    ->name('api_production_list_hourly_record_changes');
