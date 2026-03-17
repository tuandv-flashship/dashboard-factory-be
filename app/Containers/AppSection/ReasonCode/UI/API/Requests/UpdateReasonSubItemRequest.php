<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class UpdateReasonSubItemRequest extends ParentRequest
{
    protected array $decode = ['id', 'category_id'];

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:reason_categories,id'],
            'code'        => ['sometimes', 'string', 'max:100'],
            'label'       => ['sometimes', 'string', 'max:255'],
            'scope_type'  => ['sometimes', 'string', 'in:global,per_department,per_line_department'],
            'scope_line'  => ['nullable', 'string', 'max:20'],
            'scope_dept'  => ['nullable', 'string', 'max:20'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('reason-codes.edit');
    }
}
