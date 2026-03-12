<?php

namespace App\Containers\AppSection\Setting\Actions;

use App\Containers\AppSection\Setting\Tasks\GetSettingsTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetAdminAppearanceSettingsAction extends ParentAction
{
    public function __construct(
        private readonly GetSettingsTask $getSettingsTask,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return $this->getSettingsTask->run([
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
    }
}
