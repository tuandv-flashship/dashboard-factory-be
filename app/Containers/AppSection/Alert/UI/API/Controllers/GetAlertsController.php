<?php

namespace App\Containers\AppSection\Alert\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Alert\Actions\GetAlertsAction;
use App\Containers\AppSection\Alert\UI\API\Transformers\AlertTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetAlertsController extends ApiController
{
    public function __construct(
        private readonly GetAlertsAction $action,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $alerts = $this->action->run($request->query('line'));

        return Response::create($alerts, AlertTransformer::class)->ok();
    }
}
