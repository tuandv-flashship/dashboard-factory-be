<?php

namespace App\Containers\AppSection\Setting\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Setting\Actions\UpdatePhoneNumberSettingsAction;
use App\Containers\AppSection\Setting\UI\API\Requests\UpdatePhoneNumberSettingsRequest;
use App\Containers\AppSection\Setting\UI\API\Transformers\PhoneNumberSettingsTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

final class UpdatePhoneNumberSettingsController extends ApiController
{
    public function __invoke(UpdatePhoneNumberSettingsRequest $request, UpdatePhoneNumberSettingsAction $action): JsonResponse
    {
        $payload = $request->validated();

        if (array_key_exists('phone_number_available_countries', $payload) && is_array($payload['phone_number_available_countries'])) {
            $payload['phone_number_available_countries'] = json_encode(
                array_values(array_filter($payload['phone_number_available_countries'])),
                JSON_THROW_ON_ERROR,
            );
        }

        $action->run($payload);

        $settings = (object) Arr::only($payload, [
            'phone_number_enable_country_code',
            'phone_number_available_countries',
            'phone_number_min_length',
            'phone_number_max_length',
        ]);

        return Response::create()
            ->item($settings, PhoneNumberSettingsTransformer::class)
            ->ok();
    }
}
