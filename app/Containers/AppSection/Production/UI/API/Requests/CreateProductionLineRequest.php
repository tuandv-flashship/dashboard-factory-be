<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateProductionLineRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'code'       => ['required', 'string', 'max:20', 'unique:production_lines,code'],
            'label'      => ['required', 'string', 'max:50'],
            'color'      => ['required', 'string', 'max:20'],
            'subtitle'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_shared'  => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('production-lines.create');
    }
}
