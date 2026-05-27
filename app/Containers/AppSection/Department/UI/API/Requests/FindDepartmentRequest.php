<?php

namespace App\Containers\AppSection\Department\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class FindDepartmentRequest extends ParentRequest
{
    protected array $decode = ['id'];

    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('departments.index') ?? false;
    }
}
