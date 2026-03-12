<?php

/**
 * @apiGroup  Production
 * @apiName   GetDeptDetailPublic
 *
 * @api {GET} /v1/production/lines/:line/departments/:dept Get Department Detail (Public)
 */

use App\Containers\AppSection\Production\UI\API\Controllers\GetDeptDetailController;
use Illuminate\Support\Facades\Route;

Route::get('production/lines/{line}/departments/{dept}', GetDeptDetailController::class)
    ->where('line', 'dtf1|dtf2|dtg')
    ->where('dept', 'print|cut|mockup|pack_ship|pick|dtg_print')
    ->middleware('throttle:60,1');
