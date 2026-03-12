<?php

namespace App\Containers\AppSection\Setting\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Setting\Actions\GetAdminAppearanceSettingsAction;
use App\Containers\AppSection\Setting\UI\API\Requests\GetAdminAppearanceSettingsRequest;
use App\Containers\AppSection\Setting\UI\API\Transformers\AdminAppearanceSettingsTransformer;
use App\Ship\Supports\Language as LanguageSupport;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetAdminAppearanceSettingsController extends ApiController
{
    public function __invoke(GetAdminAppearanceSettingsRequest $request, GetAdminAppearanceSettingsAction $action): JsonResponse
    {
        $settings = (object) $action->run();

        return Response::create()
            ->item($settings, AdminAppearanceSettingsTransformer::class)
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
            'admin_appearance_layouts' => [
                ['value' => 'vertical', 'label' => __('settings.options.admin_appearance_layouts.vertical')],
                ['value' => 'horizontal', 'label' => __('settings.options.admin_appearance_layouts.horizontal')],
            ],
            'admin_appearance_container_widths' => [
                ['value' => 'container-xl', 'label' => __('settings.options.admin_appearance_container_widths.container_xl')],
                ['value' => 'container-3xl', 'label' => __('settings.options.admin_appearance_container_widths.container_3xl')],
                ['value' => 'container-fluid', 'label' => __('settings.options.admin_appearance_container_widths.container_fluid')],
            ],
            'rich_editors' => [
                ['value' => 'ckeditor', 'label' => __('settings.options.rich_editors.ckeditor')],
                ['value' => 'tinymce', 'label' => __('settings.options.rich_editors.tinymce')],
            ],
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
}
