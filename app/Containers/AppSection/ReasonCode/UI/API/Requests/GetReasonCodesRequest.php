<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetReasonCodesRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => '',
        'roles' => '',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Context filters (existing)
            'line'          => ['sometimes', 'nullable', 'string', 'exists:production_lines,code'],
            'dept'          => ['sometimes', 'nullable', 'string', 'exists:departments,code'],

            // New filters
            'scope_type'    => ['sometimes', 'nullable', 'string', 'in:global,per_department,per_line_department'],
            'is_active'     => ['sometimes', 'nullable', 'boolean'],
            'search'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'category_code' => ['sometimes', 'nullable', 'string', 'exists:reason_categories,code'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
