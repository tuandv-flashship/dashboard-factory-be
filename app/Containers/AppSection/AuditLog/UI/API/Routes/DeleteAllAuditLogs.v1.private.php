<?php

/**
 * @apiGroup           AuditLog
 * @apiName            DeleteAllAuditLogs
 * @api                {delete} /v1/audit-logs/empty Delete All Audit Logs
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\AuditLog\UI\API\Controllers\DeleteAllAuditLogsController;
use Illuminate\Support\Facades\Route;

Route::delete('audit-logs/empty', DeleteAllAuditLogsController::class)
    ->middleware(['auth:api']);
