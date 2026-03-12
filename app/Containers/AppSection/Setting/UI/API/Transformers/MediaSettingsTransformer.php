<?php

namespace App\Containers\AppSection\Setting\UI\API\Transformers;

use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class MediaSettingsTransformer extends ParentTransformer
{
    /**
     * @param array<string, mixed> $settings
     */
    public function transform(mixed $settings): array
    {
        if (is_object($settings)) {
            $settings = get_object_vars($settings);
        }

        if (! is_array($settings)) {
            $settings = [];
        }

        $folders = $settings['media_folders_can_add_watermark'] ?? [];

        if (is_string($folders)) {
            $decoded = json_decode($folders, true);
            $folders = is_array($decoded) ? $decoded : [];
        }

        $sizes = [];
        foreach (array_keys((array) config('media.sizes', [])) as $name) {
            $widthKey = sprintf('media_sizes_%s_width', $name);
            $heightKey = sprintf('media_sizes_%s_height', $name);

            $sizes[$name] = [
                'width' => isset($settings[$widthKey]) ? (int) $settings[$widthKey] : null,
                'height' => isset($settings[$heightKey]) ? (int) $settings[$heightKey] : null,
            ];
        }

        return [
            'type' => 'MediaSettings',
            'id' => 'media',
            'media_driver' => $settings['media_driver'] ?? null,
            'media_aws_access_key_id' => $settings['media_aws_access_key_id'] ?? null,
            'media_aws_secret_key' => $this->redactSecret($settings['media_aws_secret_key'] ?? null),
            'media_aws_default_region' => $settings['media_aws_default_region'] ?? null,
            'media_aws_bucket' => $settings['media_aws_bucket'] ?? null,
            'media_aws_url' => $settings['media_aws_url'] ?? null,
            'media_aws_endpoint' => $settings['media_aws_endpoint'] ?? null,
            'media_aws_use_path_style_endpoint' => (bool) ($settings['media_aws_use_path_style_endpoint'] ?? false),
            'media_r2_access_key_id' => $settings['media_r2_access_key_id'] ?? null,
            'media_r2_secret_key' => $this->redactSecret($settings['media_r2_secret_key'] ?? null),
            'media_r2_bucket' => $settings['media_r2_bucket'] ?? null,
            'media_r2_url' => $settings['media_r2_url'] ?? null,
            'media_r2_endpoint' => $settings['media_r2_endpoint'] ?? null,
            'media_r2_use_path_style_endpoint' => (bool) ($settings['media_r2_use_path_style_endpoint'] ?? true),
            'media_do_spaces_access_key_id' => $settings['media_do_spaces_access_key_id'] ?? null,
            'media_do_spaces_secret_key' => $this->redactSecret($settings['media_do_spaces_secret_key'] ?? null),
            'media_do_spaces_default_region' => $settings['media_do_spaces_default_region'] ?? null,
            'media_do_spaces_bucket' => $settings['media_do_spaces_bucket'] ?? null,
            'media_do_spaces_endpoint' => $settings['media_do_spaces_endpoint'] ?? null,
            'media_do_spaces_cdn_enabled' => (bool) ($settings['media_do_spaces_cdn_enabled'] ?? false),
            'media_do_spaces_cdn_custom_domain' => $settings['media_do_spaces_cdn_custom_domain'] ?? null,
            'media_do_spaces_use_path_style_endpoint' => (bool) ($settings['media_do_spaces_use_path_style_endpoint'] ?? false),
            'media_wasabi_access_key_id' => $settings['media_wasabi_access_key_id'] ?? null,
            'media_wasabi_secret_key' => $this->redactSecret($settings['media_wasabi_secret_key'] ?? null),
            'media_wasabi_default_region' => $settings['media_wasabi_default_region'] ?? null,
            'media_wasabi_bucket' => $settings['media_wasabi_bucket'] ?? null,
            'media_wasabi_root' => $settings['media_wasabi_root'] ?? null,
            'media_bunnycdn_zone' => $settings['media_bunnycdn_zone'] ?? null,
            'media_bunnycdn_hostname' => $settings['media_bunnycdn_hostname'] ?? null,
            'media_bunnycdn_key' => $this->redactSecret($settings['media_bunnycdn_key'] ?? null),
            'media_bunnycdn_region' => $settings['media_bunnycdn_region'] ?? null,
            'media_backblaze_access_key_id' => $settings['media_backblaze_access_key_id'] ?? null,
            'media_backblaze_secret_key' => $this->redactSecret($settings['media_backblaze_secret_key'] ?? null),
            'media_backblaze_default_region' => $settings['media_backblaze_default_region'] ?? null,
            'media_backblaze_bucket' => $settings['media_backblaze_bucket'] ?? null,
            'media_backblaze_endpoint' => $settings['media_backblaze_endpoint'] ?? null,
            'media_backblaze_use_path_style_endpoint' => (bool) ($settings['media_backblaze_use_path_style_endpoint'] ?? false),
            'media_backblaze_cdn_enabled' => (bool) ($settings['media_backblaze_cdn_enabled'] ?? false),
            'media_backblaze_cdn_custom_domain' => $settings['media_backblaze_cdn_custom_domain'] ?? null,
            'media_use_original_name_for_file_path' => (bool) ($settings['media_use_original_name_for_file_path'] ?? false),
            'media_convert_file_name_to_uuid' => (bool) ($settings['media_convert_file_name_to_uuid'] ?? false),
            'media_keep_original_file_size_and_quality' => (bool) ($settings['media_keep_original_file_size_and_quality'] ?? false),
            'media_turn_off_automatic_url_translation_into_latin' => (bool) ($settings['media_turn_off_automatic_url_translation_into_latin'] ?? false),
            'user_can_only_view_own_media' => (bool) ($settings['user_can_only_view_own_media'] ?? false),
            'media_convert_image_to_webp' => (bool) ($settings['media_convert_image_to_webp'] ?? false),
            'media_default_placeholder_image' => $settings['media_default_placeholder_image'] ?? null,
            'media_reduce_large_image_size' => (bool) ($settings['media_reduce_large_image_size'] ?? false),
            'media_image_max_width' => isset($settings['media_image_max_width']) ? (int) $settings['media_image_max_width'] : null,
            'media_image_max_height' => isset($settings['media_image_max_height']) ? (int) $settings['media_image_max_height'] : null,
            'media_customize_upload_path' => (bool) ($settings['media_customize_upload_path'] ?? false),
            'media_upload_path' => $settings['media_upload_path'] ?? null,
            'media_watermark_enabled' => (bool) ($settings['media_watermark_enabled'] ?? false),
            'media_folders_can_add_watermark' => $folders,
            'media_watermark_source' => $settings['media_watermark_source'] ?? null,
            'media_watermark_size' => isset($settings['media_watermark_size'])
                ? (int) $settings['media_watermark_size']
                : null,
            'media_watermark_opacity' => isset($settings['media_watermark_opacity'])
                ? (int) $settings['media_watermark_opacity']
                : null,
            'media_watermark_position' => $settings['media_watermark_position'] ?? null,
            'media_watermark_position_x' => isset($settings['media_watermark_position_x'])
                ? (int) $settings['media_watermark_position_x']
                : null,
            'media_watermark_position_y' => isset($settings['media_watermark_position_y'])
                ? (int) $settings['media_watermark_position_y']
                : null,
            'media_image_processing_library' => $settings['media_image_processing_library'] ?? 'gd',
            'media_enable_thumbnail_sizes' => (bool) ($settings['media_enable_thumbnail_sizes'] ?? true),
            'media_thumbnail_crop_position' => $settings['media_thumbnail_crop_position'] ?? null,
            'media_chunk_enabled' => (bool) ($settings['media_chunk_enabled'] ?? false),
            'media_chunk_size' => isset($settings['media_chunk_size']) ? (int) $settings['media_chunk_size'] : null,
            'media_max_file_size' => isset($settings['media_max_file_size']) ? (int) $settings['media_max_file_size'] : null,
            'media_s3_path' => $settings['media_s3_path'] ?? null,
            'media_sizes' => $sizes,
        ];
    }

    private function redactSecret(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return '********';
    }
}
