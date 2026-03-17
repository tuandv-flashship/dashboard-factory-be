<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateReasonCategoryRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'code'       => ['required', 'string', 'max:50', 'unique:reason_categories,code'],
            'label'      => ['required', 'string', 'max:255'],
            'label_en'   => ['required', 'string', 'max:255'],
            'icon'       => ['required', 'string', 'max:50'],
            'color'      => ['required', 'string', 'max:20'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('reason-codes.create');
    }
}
