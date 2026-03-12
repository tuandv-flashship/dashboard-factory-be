<?php

namespace App\Containers\AppSection\Setting\UI\API\Requests;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Supports\Language as LanguageSupport;
use DateTimeZone;
use Illuminate\Validation\Rule;

final class UpdateGeneralSettingsRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [
            'admin_email' => ['nullable', 'array'],
            'admin_email.*' => ['nullable', 'email'],
            'time_zone' => [Rule::in(DateTimeZone::listIdentifiers())],
            'enable_send_error_reporting_via_email' => ['boolean'],
            'locale_direction' => ['nullable', Rule::in(['ltr', 'rtl'])],
            'locale' => [
                'nullable',
                Rule::in(array_keys(LanguageSupport::getAvailableLocales(true))),
            ],
            'redirect_404_to_homepage' => ['boolean'],
            'request_log_data_retention_period' => ['nullable', 'integer', 'min:0'],
            'audit_log_data_retention_period' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', Setting::class);
    }
}
