<?php

namespace App\Containers\AppSection\Setting\UI\API\Requests;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class UpdateMediaSettingsRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        $rules = [
            'media_driver' => ['nullable', 'string', Rule::in([
                'public',
                'local',
                's3',
                'r2',
                'do_spaces',
                'wasabi',
                'bunnycdn',
                'backblaze',
            ])],
            'media_aws_access_key_id' => ['nullable', 'string'],
            'media_aws_secret_key' => ['nullable', 'string'],
            'media_aws_default_region' => ['nullable', 'string'],
            'media_aws_bucket' => ['nullable', 'string'],
            'media_aws_url' => ['nullable', 'string'],
            'media_aws_endpoint' => ['nullable', 'string'],
            'media_aws_use_path_style_endpoint' => ['nullable', 'boolean'],
            'media_r2_access_key_id' => ['nullable', 'string'],
            'media_r2_secret_key' => ['nullable', 'string'],
            'media_r2_bucket' => ['nullable', 'string'],
            'media_r2_url' => ['nullable', 'string'],
            'media_r2_endpoint' => ['nullable', 'string'],
            'media_r2_use_path_style_endpoint' => ['nullable', 'boolean'],
            'media_do_spaces_access_key_id' => ['nullable', 'string'],
            'media_do_spaces_secret_key' => ['nullable', 'string'],
            'media_do_spaces_default_region' => ['nullable', 'string'],
            'media_do_spaces_bucket' => ['nullable', 'string'],
            'media_do_spaces_endpoint' => ['nullable', 'string'],
            'media_do_spaces_cdn_enabled' => ['nullable', 'boolean'],
            'media_do_spaces_cdn_custom_domain' => ['nullable', 'string'],
            'media_do_spaces_use_path_style_endpoint' => ['nullable', 'boolean'],
            'media_wasabi_access_key_id' => ['nullable', 'string'],
            'media_wasabi_secret_key' => ['nullable', 'string'],
            'media_wasabi_default_region' => ['nullable', 'string'],
            'media_wasabi_bucket' => ['nullable', 'string'],
            'media_wasabi_root' => ['nullable', 'string'],
            'media_bunnycdn_zone' => ['nullable', 'string'],
            'media_bunnycdn_hostname' => ['nullable', 'string'],
            'media_bunnycdn_key' => ['nullable', 'string'],
            'media_bunnycdn_region' => ['nullable', 'string'],
            'media_backblaze_access_key_id' => ['nullable', 'string'],
            'media_backblaze_secret_key' => ['nullable', 'string'],
            'media_backblaze_default_region' => ['nullable', 'string'],
            'media_backblaze_bucket' => ['nullable', 'string'],
            'media_backblaze_endpoint' => ['nullable', 'string'],
            'media_backblaze_use_path_style_endpoint' => ['nullable', 'boolean'],
            'media_backblaze_cdn_enabled' => ['nullable', 'boolean'],
            'media_backblaze_cdn_custom_domain' => ['nullable', 'string'],
            'media_use_original_name_for_file_path' => ['nullable', 'boolean'],
            'media_convert_file_name_to_uuid' => ['nullable', 'boolean'],
            'media_keep_original_file_size_and_quality' => ['nullable', 'boolean'],
            'media_turn_off_automatic_url_translation_into_latin' => ['nullable', 'boolean'],
            'user_can_only_view_own_media' => ['nullable', 'boolean'],
            'media_convert_image_to_webp' => ['nullable', 'boolean'],
            'media_default_placeholder_image' => ['nullable', 'string'],
            'media_reduce_large_image_size' => ['nullable', 'boolean'],
            'media_image_max_width' => ['nullable', 'integer', 'min:0'],
            'media_image_max_height' => ['nullable', 'integer', 'min:0'],
            'media_customize_upload_path' => ['nullable', 'boolean'],
            'media_upload_path' => ['nullable', 'string'],
            'media_watermark_enabled' => ['nullable', 'boolean'],
            'media_folders_can_add_watermark' => ['nullable', 'array'],
            'media_folders_can_add_watermark.*' => ['integer', 'min:1'],
            'media_watermark_source' => ['nullable', 'string'],
            'media_watermark_size' => ['nullable', 'integer', 'min:0'],
            'media_watermark_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'media_watermark_position' => ['nullable', 'string', Rule::in([
                'top-left',
                'top-right',
                'bottom-left',
                'bottom-right',
                'center',
            ])],
            'media_watermark_position_x' => ['nullable', 'integer', 'min:0'],
            'media_watermark_position_y' => ['nullable', 'integer', 'min:0'],
            'media_image_processing_library' => ['nullable', 'string', Rule::in(['gd'])],
            'media_enable_thumbnail_sizes' => ['nullable', 'boolean'],
            'media_thumbnail_crop_position' => ['nullable', 'string', Rule::in(['left', 'right', 'top', 'bottom', 'center'])],
            'media_chunk_enabled' => ['nullable', 'boolean'],
            'media_chunk_size' => ['nullable', 'integer', 'min:0'],
            'media_max_file_size' => ['nullable', 'integer', 'min:0'],
            'media_s3_path' => ['nullable', 'string'],
            'media_sizes' => ['nullable', 'array'],
        ];

        foreach (array_keys((array) config('media.sizes', [])) as $name) {
            $rules[sprintf('media_sizes_%s_width', $name)] = ['nullable', 'integer', 'min:0'];
            $rules[sprintf('media_sizes_%s_height', $name)] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', Setting::class);
    }
}
