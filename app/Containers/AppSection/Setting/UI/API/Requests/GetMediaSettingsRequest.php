<?php

namespace App\Containers\AppSection\Setting\UI\API\Requests;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetMediaSettingsRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Setting::class);
    }
}
