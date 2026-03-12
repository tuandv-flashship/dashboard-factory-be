<?php

/**
 * @apiGroup  Production
 * @apiName   GetDeptDetail
 *
 * @api {GET} /v1/production/lines/:line/departments/:dept Get Department Detail
 *
 * @apiDescription Get hourly production data with KPI miss issues for a specific department.
 *
 * @apiParam {String="dtf1","dtf2","dtg"} line Production line code
 * @apiParam {String="print","cut","mockup","pack_ship","pick","dtg_print"} dept Department code
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetDeptDetailController;
use Illuminate\Support\Facades\Route;

Route::get('production/lines/{line}/departments/{dept}', GetDeptDetailController::class)
    ->middleware(['auth:api'])
    ->whereIn('line', ['dtf1', 'dtf2', 'dtg'])
    ->whereIn('dept', ['print', 'cut', 'mockup', 'pack_ship', 'pick', 'dtg_print']);
