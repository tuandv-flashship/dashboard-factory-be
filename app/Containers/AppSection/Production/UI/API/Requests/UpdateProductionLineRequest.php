<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class UpdateProductionLineRequest extends ParentRequest
{
    protected array $decode = ['id'];

    public function rules(): array
    {
        return [
            'code'       => ['sometimes', 'string', 'max:20', Rule::unique('production_lines', 'code')->ignore($this->id)],
            'label'      => ['sometimes', 'string', 'max:50'],
            'color'      => ['sometimes', 'string', 'max:20'],
            'subtitle'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('production-lines.edit');
    }
}
