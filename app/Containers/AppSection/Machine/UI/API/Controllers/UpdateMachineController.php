<?php

namespace App\Containers\AppSection\Machine\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Machine\UI\API\Requests\UpdateMachineRequest;
use App\Containers\AppSection\Machine\UI\API\Transformers\MachineTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateMachineController extends ApiController
{
    public function __invoke(UpdateMachineRequest $request): JsonResponse
    {
        $machine = Machine::findOrFail($request->id);

        $machine->update($request->validated());

        return Response::create($machine, MachineTransformer::class)->ok();
    }
}
