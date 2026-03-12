<?php

namespace App\Containers\AppSection\Setting\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Setting\Actions\UpdateGeneralSettingsAction;
use App\Containers\AppSection\Setting\UI\API\Requests\UpdateGeneralSettingsRequest;
use App\Containers\AppSection\Setting\UI\API\Transformers\GeneralSettingsTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

final class UpdateGeneralSettingsController extends ApiController
{
    public function __invoke(UpdateGeneralSettingsRequest $request, UpdateGeneralSettingsAction $action): JsonResponse
    {
        $payload = $request->validated();

        if (array_key_exists('admin_email', $payload) && is_array($payload['admin_email'])) {
            $payload['admin_email'] = json_encode(array_values(array_filter($payload['admin_email'])), JSON_THROW_ON_ERROR);
        }

        $action->run($payload);

        $settings = (object) Arr::only($payload, [
            'admin_email',
            'time_zone',
            'enable_send_error_reporting_via_email',
            'locale_direction',
            'locale',
            'redirect_404_to_homepage',
            'request_log_data_retention_period',
            'audit_log_data_retention_period',
        ]);

        return Response::create()
            ->item($settings, GeneralSettingsTransformer::class)
            ->ok();
    }
}
