<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ResyncHourlyRecordsRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => '',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [
            'date'            => ['nullable', 'date_format:Y-m-d'],
            'shift'           => ['nullable', 'integer', 'min:1'],
            'shift_detail_id' => ['nullable', 'integer', 'exists:shift_details,id'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('production.crud');
    }
}
