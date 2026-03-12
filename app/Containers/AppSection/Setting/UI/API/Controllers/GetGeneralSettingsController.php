<?php

namespace App\Containers\AppSection\Setting\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Setting\Actions\GetGeneralSettingsAction;
use App\Containers\AppSection\Setting\UI\API\Requests\GetGeneralSettingsRequest;
use App\Containers\AppSection\Setting\UI\API\Transformers\GeneralSettingsTransformer;
use App\Ship\Supports\Language as LanguageSupport;
use App\Ship\Parents\Controllers\ApiController;
use DateTimeZone;
use Illuminate\Http\JsonResponse;

final class GetGeneralSettingsController extends ApiController
{
    public function __invoke(GetGeneralSettingsRequest $request, GetGeneralSettingsAction $action): JsonResponse
    {
        $settings = (object) $action->run();

        return Response::create()
            ->item($settings, GeneralSettingsTransformer::class)
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
            'locale_directions' => [
                ['value' => 'ltr', 'label' => __('settings.options.locale_directions.ltr')],
                ['value' => 'rtl', 'label' => __('settings.options.locale_directions.rtl')],
            ],
            'locales' => $this->buildLocaleOptions(),
            'timezones' => $this->buildTimezoneOptions(),
        ];
    }

    private function buildLocaleOptions(): array
    {
        $locales = [];
        foreach (LanguageSupport::getAvailableLocales(true) as $locale => $data) {
            $value = $data['locale'] ?? $locale;
            $locales[] = [
                'value' => $value,
                'label' => $data['name'] ?? $value,
                'code' => $data['code'] ?? null,
                'flag' => $data['flag'] ?? null,
                'is_rtl' => (bool) ($data['is_rtl'] ?? false),
            ];
        }

        return $locales;
    }

    private function buildTimezoneOptions(): array
    {
        $options = [];
        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            $options[] = [
                'value' => $timezone,
                'label' => $timezone,
            ];
        }

        return $options;
    }
}
