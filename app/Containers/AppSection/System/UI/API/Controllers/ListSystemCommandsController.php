<?php

namespace App\Containers\AppSection\System\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\System\Actions\ListSystemCommandsAction;
use App\Containers\AppSection\System\UI\API\Requests\ListSystemCommandsRequest;
use App\Containers\AppSection\System\UI\API\Transformers\SystemCommandTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class ListSystemCommandsController extends ApiController
{
    public function __invoke(ListSystemCommandsRequest $request, ListSystemCommandsAction $action): JsonResponse
    {
        $commands = $action->run();

        return Response::create($commands, SystemCommandTransformer::class)->ok();
    }
}
