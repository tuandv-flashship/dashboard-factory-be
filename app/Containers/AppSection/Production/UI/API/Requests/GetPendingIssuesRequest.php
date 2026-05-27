<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetPendingIssuesRequest extends ParentRequest
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
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('hourly-issues.index') ?? false;
    }
}
