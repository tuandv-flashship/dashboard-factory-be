<?php

/**
 * @apiGroup  Production
 * @apiName   GetProductionSchedulerSettings
 *
 * @api {GET} /v1/admin/production/scheduler-settings Get Scheduler Settings
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetProductionSchedulerSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('admin/production/scheduler-settings', GetProductionSchedulerSettingsController::class)
    ->middleware(['auth:api']);
