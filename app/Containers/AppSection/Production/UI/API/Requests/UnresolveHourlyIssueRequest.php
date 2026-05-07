<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class UnresolveHourlyIssueRequest extends ParentRequest
{
    protected array $decode = ['id'];

    protected array $access = [
        'permissions' => '',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()->can('hourly-issues.resolve');
    }
}
