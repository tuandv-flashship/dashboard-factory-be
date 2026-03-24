<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ListDepartmentsRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()->can('departments.index');
    }
}
