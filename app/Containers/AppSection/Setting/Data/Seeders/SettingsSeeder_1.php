<?php

namespace App\Containers\AppSection\Setting\Data\Seeders;

use App\Containers\AppSection\Setting\Tasks\UpsertSettingsTask;
use App\Containers\AppSection\Setting\Models\Setting;
use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Ship\Parents\Seeders\Seeder as ParentSeeder;

final class SettingsSeeder_1 extends ParentSeeder
{
    public function run(UpsertSettingsTask $task, MediaSettingsStore $settingsStore): void
    {
        $force = filter_var(env('FORCE_SETTINGS_SEED', false), FILTER_VALIDATE_BOOLEAN);
        $mediaDefaults = $this->normalizeMediaDefaults((array) config('media.settings_defaults', []));
        $mediaSizeDefaults = $this->resolveMediaSizeDefaults();
        $mediaSeed = array_merge($mediaDefaults, $mediaSizeDefaults);
        $keys = [
            'admin_email',
            'time_zone',
            'locale_direction',
            'locale',
            'enable_send_error_reporting_via_email',
            'redirect_404_to_homepage',
            'request_log_data_retention_period',
            'audit_log_data_retention_period',
            'phone_number_enable_country_code',
            'phone_number_available_countries',
            'phone_number_min_length',
            'phone_number_max_length',
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
        ];
        $keys = array_merge($keys, array_keys($mediaSeed));

        if (!$force && Setting::query()->whereIn('key', $keys)->exists()) {
            return;
        }

        $task->run(array_merge([
            'admin_email' => json_encode([
                'dvt.soict@gmail.com',
                'dvt.hust@gmail.com',
            ], JSON_THROW_ON_ERROR),
            'time_zone' => 'Asia/Ho_Chi_Minh',
            'locale_direction' => 'ltr',
            'locale' => 'vi',
            'enable_send_error_reporting_via_email' => 0,
            'redirect_404_to_homepage' => 0,
            'request_log_data_retention_period' => 30,
            'audit_log_data_retention_period' => 30,
            'phone_number_enable_country_code' => 1,
            'phone_number_available_countries' => json_encode(['US', 'VN'], JSON_THROW_ON_ERROR),
            'phone_number_min_length' => 8,
            'phone_number_max_length' => 15,
            'admin_logo' => 'logo.png',
            'admin_logo_max_height' => 32,
            'admin_favicon' => 'general/favicon.png',
            'admin_favicon_type' => 'image/x-icon',
            'login_screen_backgrounds' => json_encode([
                'the-illustration-graphic-consists-of-abstract-back.jpg',
                'gradient-abstract-wireframe-background-23-21490099.jpg',
            ], JSON_THROW_ON_ERROR),
            'admin_title' => 'Admin App',
            'admin_primary_font' => 'Inter',
            'admin_primary_color' => '#206bc4',
            'admin_secondary_color' => '#6c7a91',
            'admin_heading_color' => 'inherit',
            'admin_text_color' => '#182433',
            'admin_link_color' => '#206bc4',
            'admin_link_hover_color' => '#1a569d',
            'admin_appearance_locale' => 'vi',
            'admin_appearance_locale_direction' => 'ltr',
            'rich_editor' => 'ckeditor',
            'enable_page_visual_builder' => 1,
            'admin_appearance_layout' => 'horizontal',
            'admin_appearance_container_width' => 'container-fluid',
            'admin_appearance_show_menu_item_icon' => 1,
            'show_admin_bar' => 1,
            'show_theme_guideline_link' => 0,
            'admin_appearance_custom_css' => '',
            'admin_appearance_custom_header_js' => '',
            'admin_appearance_custom_body_js' => '',
            'admin_appearance_custom_footer_js' => '',
        ], $mediaSeed));

        $settingsStore->clear();
    }

    /**
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private function normalizeMediaDefaults(array $defaults): array
    {
        foreach ($this->sensitiveMediaKeys() as $key) {
            unset($defaults[$key]);
        }

        foreach ($defaults as $key => $value) {
            if (is_array($value)) {
                $defaults[$key] = json_encode($value, JSON_THROW_ON_ERROR);
                continue;
            }

            if (is_bool($value)) {
                $defaults[$key] = $value ? 1 : 0;
            }
        }

        return $defaults;
    }

    /**
     * @return array<int, string>
     */
    private function sensitiveMediaKeys(): array
    {
        return [
            'media_aws_secret_key',
            'media_r2_secret_key',
            'media_do_spaces_secret_key',
            'media_wasabi_secret_key',
            'media_bunnycdn_key',
            'media_backblaze_secret_key',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function resolveMediaSizeDefaults(): array
    {
        $defaults = [];
        $sizes = (array) config('media.sizes', []);

        foreach ($sizes as $name => $size) {
            $parts = explode('x', strtolower((string) $size));
            if (count($parts) !== 2) {
                continue;
            }

            $width = $parts[0] === 'auto' ? 0 : (int) $parts[0];
            $height = $parts[1] === 'auto' ? 0 : (int) $parts[1];

            $defaults[sprintf('media_sizes_%s_width', $name)] = $width;
            $defaults[sprintf('media_sizes_%s_height', $name)] = $height;
        }

        return $defaults;
    }
}
