<?php

namespace App\Containers\AppSection\Department\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ReorderDepartmentsRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'items'              => ['required', 'array', 'min:1'],
            'items.*.id'         => ['required', 'integer', 'exists:departments,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('departments.edit');
    }
}
