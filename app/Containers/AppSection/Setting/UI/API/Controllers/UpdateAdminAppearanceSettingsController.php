<?php

namespace App\Containers\AppSection\Setting\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Setting\Actions\UpdateAdminAppearanceSettingsAction;
use App\Containers\AppSection\Setting\UI\API\Requests\UpdateAdminAppearanceSettingsRequest;
use App\Containers\AppSection\Setting\UI\API\Transformers\AdminAppearanceSettingsTransformer;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

final class UpdateAdminAppearanceSettingsController extends ApiController
{
    public function __invoke(
        UpdateAdminAppearanceSettingsRequest $request,
        UpdateAdminAppearanceSettingsAction $action
    ): JsonResponse {
        $payload = $request->validated();

        if (array_key_exists('login_screen_backgrounds', $payload) && is_array($payload['login_screen_backgrounds'])) {
            $payload['login_screen_backgrounds'] = json_encode(
                array_values(array_filter($payload['login_screen_backgrounds'])),
                JSON_THROW_ON_ERROR,
            );
        }

        $action->run($payload);

        $settings = (object) Arr::only($payload, [
            'admin_logo',
            'admin_logo_max_height',
            'admin_favicon',
            'admin_favicon_type',
            'login_screen_backgrounds',
            'admin_title',
            'admin_primary_font',
            'admin_primary_color',
            'admin_secondary_color',
            'admin_heading_color',
            'admin_text_color',
            'admin_link_color',
            'admin_link_hover_color',
            'admin_appearance_locale',
            'admin_appearance_locale_direction',
            'rich_editor',
            'enable_page_visual_builder',
            'admin_appearance_layout',
            'admin_appearance_container_width',
            'admin_appearance_show_menu_item_icon',
            'show_admin_bar',
            'show_theme_guideline_link',
            'admin_appearance_custom_css',
            'admin_appearance_custom_header_js',
            'admin_appearance_custom_body_js',
            'admin_appearance_custom_footer_js',
        ]);

        return Response::create()
            ->item($settings, AdminAppearanceSettingsTransformer::class)
            ->ok();
    }
}
