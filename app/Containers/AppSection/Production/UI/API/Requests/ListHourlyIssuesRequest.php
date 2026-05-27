<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ListHourlyIssuesRequest extends ParentRequest
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
            'resolved'      => ['nullable', 'boolean'],
            'date_from'     => ['nullable', 'date_format:Y-m-d'],
            'date_to'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('hourly-issues.index') ?? false;
    }
}
