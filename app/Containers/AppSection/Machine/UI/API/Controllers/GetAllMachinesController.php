<?php

namespace App\Containers\AppSection\Machine\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Machine\Actions\GetAllMachinesAction;
use App\Containers\AppSection\Machine\UI\API\Transformers\MachineTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetAllMachinesController extends ApiController
{
    public function __construct(
        private readonly GetAllMachinesAction $action,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $machines = $this->action->run();

        return Response::create($machines, MachineTransformer::class)->ok();
    }
}
