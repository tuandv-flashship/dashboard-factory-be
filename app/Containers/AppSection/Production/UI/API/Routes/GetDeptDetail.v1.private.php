<?php

/**
 * @apiGroup  Production
 * @apiName   GetDeptDetail
 *
 * @api {GET} /v1/production/lines/:line/departments/:dept Get Department Detail
 *
 * @apiDescription Get hourly production data with KPI miss issues for a specific department.
 *
 * @apiParam {String} line Production line code (e.g. dtf, dtg, pack_ship)
 * @apiParam {String} dept Department code (e.g. print, cut, mockup, pick, pack_ship)
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetDeptDetailController;
use Illuminate\Support\Facades\Route;

Route::get('production/lines/{line}/departments/{dept}', GetDeptDetailController::class)
    ->middleware(['auth:api'])
    ->where('line', '[a-z_]+')
    ->where('dept', '[a-z_0-9]+');
