<?php

namespace App\Containers\AppSection\Machine\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Machine\Actions\GetMachinesByLineAction;
use App\Containers\AppSection\Machine\UI\API\Transformers\MachineTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetMachinesByLineController extends ApiController
{
    public function __construct(
        private readonly GetMachinesByLineAction $action,
    ) {
    }

    public function __invoke(string $line): JsonResponse
    {
        $machines = $this->action->run($line);

        return Response::create($machines, MachineTransformer::class)->ok();
    }
}
