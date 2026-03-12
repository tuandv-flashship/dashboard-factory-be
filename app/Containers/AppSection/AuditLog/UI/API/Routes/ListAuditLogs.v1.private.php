<?php

/**
 * @apiGroup           AuditLog
 * @apiName            ListAuditLogs
 * @api                {get} /v1/audit-logs List Audit Logs
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 * @apiParam           {Number} [page]
 * @apiParam           {Number} [limit]
 * @apiUse             AuditLogResponse
 */

use App\Containers\AppSection\AuditLog\UI\API\Controllers\ListAuditLogsController;
use Illuminate\Support\Facades\Route;

Route::get('audit-logs', ListAuditLogsController::class)
    ->middleware(['auth:api']);
