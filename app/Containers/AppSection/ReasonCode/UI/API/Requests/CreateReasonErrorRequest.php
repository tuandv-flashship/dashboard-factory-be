<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateReasonErrorRequest extends ParentRequest
{
    protected array $decode = ['category_id', 'sub_item_id'];

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:reason_categories,id'],
            'sub_item_id' => ['required', 'integer', 'exists:reason_sub_items,id'],
            'code'        => ['required', 'string', 'max:100'],
            'label'       => ['required', 'string', 'max:255'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('reason-codes.create') ?? false;
    }
}
