<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Containers\AppSection\Production\Enums\DepartmentUnit;
use App\Containers\AppSection\Production\Enums\Factory;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class CreateDepartmentRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'production_line_id' => ['required', 'integer', 'exists:production_lines,id'],
            'code'               => ['required', 'string', 'max:30', Rule::unique('departments')->where('production_line_id', $this->production_line_id)],
            'label'              => ['required', 'string', 'max:50'],
            'label_en'           => ['required', 'string', 'max:50'],
            'icon'               => ['required', 'string', 'max:30'],
            'unit'               => ['required', Rule::enum(DepartmentUnit::class)],
            'kpi_per_hour'       => ['sometimes', 'integer', 'min:0'],
            'factory'            => ['sometimes', Rule::enum(Factory::class)],
            'sort_order'         => ['sometimes', 'integer', 'min:0'],
            'is_active'          => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('departments.create');
    }
}
