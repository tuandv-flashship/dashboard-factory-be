<?php

namespace App\Containers\AppSection\Machine\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Machine\Actions\UpdateMachineStatusAction;
use App\Containers\AppSection\Machine\UI\API\Requests\UpdateMachineStatusRequest;
use App\Containers\AppSection\Machine\UI\API\Transformers\MachineTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateMachineStatusController extends ApiController
{
    public function __construct(
        private readonly UpdateMachineStatusAction $action,
    ) {
    }

    public function __invoke(UpdateMachineStatusRequest $request, int $id): JsonResponse
    {
        $machine = $this->action->run($id, $request->validated('status'));

        return Response::create($machine, MachineTransformer::class)->ok();
    }
}
