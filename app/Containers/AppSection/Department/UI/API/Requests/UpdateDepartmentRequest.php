<?php

namespace App\Containers\AppSection\Department\UI\API\Requests;

use App\Containers\AppSection\Department\Enums\DepartmentUnit;
use App\Containers\AppSection\Department\Enums\Factory;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class UpdateDepartmentRequest extends ParentRequest
{
    protected array $decode = ['id'];

    public function rules(): array
    {
        return [
            'production_line_id' => ['sometimes', 'integer', 'exists:production_lines,id'],
            'code'               => ['sometimes', 'string', 'max:30', Rule::unique('departments')->where('production_line_id', $this->production_line_id ?? $this->route('id'))->ignore($this->id)],
            'label'              => ['sometimes', 'string', 'max:50'],
            'label_en'           => ['sometimes', 'string', 'max:50'],
            'icon'               => ['sometimes', 'string', 'max:30'],
            'unit'               => ['sometimes', Rule::enum(DepartmentUnit::class)],
            'kpi_per_hour'       => ['sometimes', 'integer', 'min:0'],
            'factory'            => ['sometimes', Rule::enum(Factory::class)],
            'sort_order'         => ['sometimes', 'integer', 'min:0'],
            'is_active'          => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('departments.edit');
    }
}
