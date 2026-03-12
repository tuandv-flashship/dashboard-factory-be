<?php

namespace App\Containers\AppSection\Setting\UI\API\Requests;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Ship\Parents\Requests\Request as ParentRequest;

final class UpdatePhoneNumberSettingsRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [
            'phone_number_enable_country_code' => ['boolean'],
            'phone_number_available_countries' => ['nullable', 'array'],
            'phone_number_available_countries.*' => ['required', 'string', 'size:2', 'alpha'],
            'phone_number_min_length' => ['nullable', 'integer', 'min:1'],
            'phone_number_max_length' => ['nullable', 'integer', 'min:1', 'gte:phone_number_min_length'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', Setting::class);
    }
}
