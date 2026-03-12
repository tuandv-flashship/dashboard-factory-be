<?php

namespace App\Containers\AppSection\System\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\System\Actions\GetSystemCommandStatusAction;
use App\Containers\AppSection\System\UI\API\Requests\GetSystemCommandStatusRequest;
use App\Containers\AppSection\System\UI\API\Transformers\SystemCommandResultTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetSystemCommandStatusController extends ApiController
{
    public function __invoke(
        GetSystemCommandStatusRequest $request,
        GetSystemCommandStatusAction $action
    ): JsonResponse {
        $jobId = (string) $request->route('job_id');
        $result = $action->run($jobId);

        if ($result === null) {
            abort(404, 'System command job not found.');
        }

        return Response::create()
            ->item((object) $result, SystemCommandResultTransformer::class)
            ->ok();
    }
}
