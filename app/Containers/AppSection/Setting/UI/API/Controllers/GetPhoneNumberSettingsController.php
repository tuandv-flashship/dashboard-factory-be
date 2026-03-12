<?php

namespace App\Containers\AppSection\Setting\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Setting\Actions\GetPhoneNumberSettingsAction;
use App\Containers\AppSection\Setting\UI\API\Requests\GetPhoneNumberSettingsRequest;
use App\Containers\AppSection\Setting\UI\API\Transformers\PhoneNumberSettingsTransformer;
use App\Ship\Supports\Language as LanguageSupport;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetPhoneNumberSettingsController extends ApiController
{
    public function __invoke(GetPhoneNumberSettingsRequest $request, GetPhoneNumberSettingsAction $action): JsonResponse
    {
        $settings = (object) $action->run();

        return Response::create()
            ->item($settings, PhoneNumberSettingsTransformer::class)
            ->addMeta([
                'options' => $this->buildOptions(),
            ])
            ->ok();
    }

    private function buildOptions(): array
    {
        return [
            'boolean' => [
                ['value' => 1, 'label' => __('settings.options.boolean.on')],
                ['value' => 0, 'label' => __('settings.options.boolean.off')],
            ],
            'country_codes' => $this->buildCountryOptions(),
        ];
    }

    private function buildCountryOptions(): array
    {
        $flags = LanguageSupport::getListLanguageFlags();
        $options = [];

        foreach ($flags as $code => $name) {
            if (! preg_match('/^[a-z]{2}$/i', (string) $code)) {
                continue;
            }

            $options[] = [
                'value' => strtoupper((string) $code),
                'label' => (string) $name,
            ];
        }

        usort($options, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }
}
