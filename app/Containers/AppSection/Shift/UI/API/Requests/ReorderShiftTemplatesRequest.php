<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ReorderShiftTemplatesRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'items'              => ['required', 'array', 'min:1'],
            'items.*.id'         => ['required', 'integer', 'exists:shift_templates,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('shift-templates.edit');
    }
}
