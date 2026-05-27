<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetProductionSchedulerSettingsRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => '',
        'roles'       => 'admin',
    ];

    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Setting::class) ?? false;
    }
}
