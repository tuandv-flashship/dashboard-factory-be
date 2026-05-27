<?php

namespace App\Containers\AppSection\Department\UI\API\Requests;

use App\Containers\AppSection\Department\Enums\DepartmentUnit;
use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CreateDepartmentRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:50'],
            'production_line_id' => ['required', 'integer', 'exists:production_lines,id'],
            'description'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'kpi_per_hour'      => ['sometimes', 'integer', 'min:0'],
            'unit'              => ['sometimes', Rule::enum(DepartmentUnit::class)],
            'sort_order'        => ['sometimes', 'integer', 'min:0'],
            'productivity_type' => ['sometimes', Rule::enum(ProductivityType::class)],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('departments.create') ?? false;
    }

    /**
     * Get the generated slug code for uniqueness validation.
     */
    public function getSlugCode(): string
    {
        return Str::slug($this->name);
    }
}
