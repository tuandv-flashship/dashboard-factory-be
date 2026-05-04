<?php
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = \Illuminate\Http\Request::create('/v1/reason-codes?line=dtg&dept=dtg_print&include=sub_items.errors', 'GET');
app()->instance('request', $request);

$controller = app()->make(\App\Containers\AppSection\ReasonCode\UI\API\Controllers\GetReasonCodesController::class);

$response = $controller($request);
echo json_encode($response->getData(true), JSON_PRETTY_PRINT);
