<?php

/**
 * @apiGroup  Production
 * @apiName   UpdateProductionSchedulerSettings
 *
 * @api {PUT} /v1/admin/production/scheduler-settings Update Scheduler Settings
 */

use App\Containers\AppSection\Production\UI\API\Controllers\UpdateProductionSchedulerSettingsController;
use Illuminate\Support\Facades\Route;

Route::put('admin/production/scheduler-settings', UpdateProductionSchedulerSettingsController::class)
    ->middleware(['auth:api']);
