<?php

namespace App\Containers\AppSection\Setting\UI\API\Requests;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Supports\Language as LanguageSupport;
use Illuminate\Validation\Rule;

final class UpdateAdminAppearanceSettingsRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [
            'admin_logo' => ['nullable', 'string'],
            'admin_logo_max_height' => ['nullable', 'integer', 'min:1'],
            'admin_favicon' => ['nullable', 'string'],
            'admin_favicon_type' => ['nullable', 'string'],
            'login_screen_backgrounds' => ['nullable', 'array'],
            'login_screen_backgrounds.*' => ['required', 'string'],
            'admin_title' => ['nullable', 'string'],
            'admin_primary_font' => ['nullable', 'string'],
            'admin_primary_color' => ['nullable', 'string'],
            'admin_secondary_color' => ['nullable', 'string'],
            'admin_heading_color' => ['nullable', 'string'],
            'admin_text_color' => ['nullable', 'string'],
            'admin_link_color' => ['nullable', 'string'],
            'admin_link_hover_color' => ['nullable', 'string'],
            'admin_appearance_locale' => [
                'nullable',
                Rule::in(array_keys(LanguageSupport::getAvailableLocales(true))),
            ],
            'admin_appearance_locale_direction' => ['nullable', Rule::in(['ltr', 'rtl'])],
            'rich_editor' => ['nullable', Rule::in(['ckeditor', 'tinymce'])],
            'enable_page_visual_builder' => ['boolean'],
            'admin_appearance_layout' => ['nullable', Rule::in(['vertical', 'horizontal'])],
            'admin_appearance_container_width' => [
                'nullable',
                Rule::in(['container-xl', 'container-3xl', 'container-fluid']),
            ],
            'admin_appearance_show_menu_item_icon' => ['boolean'],
            'show_admin_bar' => ['boolean'],
            'show_theme_guideline_link' => ['boolean'],
            'admin_appearance_custom_css' => ['nullable', 'string'],
            'admin_appearance_custom_header_js' => ['nullable', 'string'],
            'admin_appearance_custom_body_js' => ['nullable', 'string'],
            'admin_appearance_custom_footer_js' => ['nullable', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', Setting::class);
    }
}
