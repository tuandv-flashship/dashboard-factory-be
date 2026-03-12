<?php

/**
 * @apiGroup           AuditLog
 * @apiName            DeleteAuditLog
 * @api                {delete} /v1/audit-logs/:audit_log_id Delete Audit Log
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 */

use App\Containers\AppSection\AuditLog\UI\API\Controllers\DeleteAuditLogController;
use Illuminate\Support\Facades\Route;

Route::delete('audit-logs/{audit_log_id}', DeleteAuditLogController::class)
    ->middleware(['auth:api']);
