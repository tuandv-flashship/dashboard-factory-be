<?php

namespace App\Containers\AppSection\System\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\System\Actions\GetSystemPackagesAction;
use App\Containers\AppSection\System\UI\API\Requests\GetSystemPackagesRequest;
use App\Containers\AppSection\System\UI\API\Transformers\SystemPackageTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetSystemPackagesController extends ApiController
{
    public function __invoke(GetSystemPackagesRequest $request, GetSystemPackagesAction $action): JsonResponse
    {
        $packages = $action->run($request->validated(), $request->query());

        return Response::create($packages, SystemPackageTransformer::class)->ok();
    }
}
