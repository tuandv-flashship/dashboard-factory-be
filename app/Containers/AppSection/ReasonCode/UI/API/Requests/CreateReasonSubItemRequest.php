<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateReasonSubItemRequest extends ParentRequest
{
    protected array $decode = ['category_id'];

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:reason_categories,id'],
            'code'        => ['required', 'string', 'max:100'],
            'label'       => ['required', 'string', 'max:255'],
            'scope_type'  => ['required', 'string', 'in:global,per_department,per_line_department'],
            'scope_line'  => ['nullable', 'string', 'max:20'],
            'scope_dept'  => ['nullable', 'string', 'max:20'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('reason-codes.create');
    }
}
