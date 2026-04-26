<?php

/**
 * @apiGroup  Machine
 * @apiName   UpdateMachine
 *
 * @api {PATCH} /v1/admin/machines/:id Update Machine
 *
 * @apiDescription Update machine details. Partial update — only submitted fields are changed.
 *                 Changes apply to future shifts only (existing shift_detail_machines are snapshots).
 *
 * @apiParam  {String} id Machine hashed ID
 * @apiBody   {Integer} [department_id] Department ID (must exist in departments table)
 * @apiBody   {String}  [code] Machine code, max 50
 * @apiBody   {String}  [name] Machine name, max 255
 * @apiBody   {String}  [description] Machine description, nullable
 * @apiBody   {String}  [unit] Unit of measurement, max 50
 * @apiBody   {Integer} [kpi_per_hour] KPI per hour, min 0
 * @apiBody   {Integer} [sort_order] Sort order, min 0
 * @apiBody   {Boolean} [is_active] Whether machine is active
 * @apiBody   {String="online","offline","maintenance"} [status] Machine operational status
 */

use App\Containers\AppSection\Machine\UI\API\Controllers\UpdateMachineController;
use Illuminate\Support\Facades\Route;

Route::patch('admin/machines/{id}', UpdateMachineController::class)
    ->middleware(['auth:api']);
