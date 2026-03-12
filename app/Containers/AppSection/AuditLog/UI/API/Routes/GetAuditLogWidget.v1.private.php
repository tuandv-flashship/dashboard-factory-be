<?php

/**
 * @apiGroup           AuditLog
 * @apiName            GetAuditLogWidget
 * @api                {get} /v1/audit-logs/widgets/activities Audit Log Widget
 * @apiVersion         1.0.0
 * @apiPermission      Authenticated ['permissions' => null, 'roles' => null]
 * @apiHeader          {String} accept=application/json
 * @apiHeader          {String} authorization=Bearer
 * @apiParam           {Number} [page]
 * @apiParam           {Number} [limit]
 * @apiUse             AuditLogResponse
 */

use App\Containers\AppSection\AuditLog\UI\API\Controllers\GetAuditLogWidgetController;
use Illuminate\Support\Facades\Route;

Route::get('audit-logs/widgets/activities', GetAuditLogWidgetController::class)
    ->middleware(['auth:api']);
