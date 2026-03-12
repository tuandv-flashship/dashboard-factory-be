<?php

namespace App\Containers\AppSection\Setting\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Setting\Actions\UpdateMediaSettingsAction;
use App\Containers\AppSection\Setting\UI\API\Requests\UpdateMediaSettingsRequest;
use App\Containers\AppSection\Setting\UI\API\Transformers\MediaSettingsTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class UpdateMediaSettingsController extends ApiController
{
    public function __invoke(UpdateMediaSettingsRequest $request, UpdateMediaSettingsAction $action): JsonResponse
    {
        $settings = (object) $action->run($request->validated());

        return Response::create()
            ->item($settings, MediaSettingsTransformer::class)
            ->ok();
    }
}
