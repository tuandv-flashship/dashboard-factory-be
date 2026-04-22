<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class UpdateProductionSchedulerSettingsRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => '',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [
            'in_shift_interval'        => ['sometimes', 'integer', 'min:0', 'max:60'],
            'off_shift_interval'       => ['sometimes', 'integer', 'min:0', 'max:60'],
            'off_shift_before_minutes' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'off_shift_after_minutes'  => ['sometimes', 'integer', 'min:0', 'max:120'],
            'daily_shift_job_at'       => ['sometimes', 'string', 'date_format:H:i'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
