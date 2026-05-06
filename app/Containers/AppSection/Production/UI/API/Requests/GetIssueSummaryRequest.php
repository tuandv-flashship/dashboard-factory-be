<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetIssueSummaryRequest extends ParentRequest
{
    protected array $decode = ['department_id'];

    protected array $access = [
        'permissions' => '',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [
            'date'          => ['nullable', 'date_format:Y-m-d'],
            'shift'         => ['nullable', 'integer', 'min:1'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'category'      => ['nullable', 'string', 'max:50'],
            'date_from'     => ['nullable', 'date_format:Y-m-d'],
            'date_to'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
