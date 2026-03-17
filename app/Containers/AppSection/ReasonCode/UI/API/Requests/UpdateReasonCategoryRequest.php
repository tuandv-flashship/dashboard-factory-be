<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class UpdateReasonCategoryRequest extends ParentRequest
{
    protected array $decode = ['id'];

    public function rules(): array
    {
        return [
            'code'       => ['sometimes', 'string', 'max:50', Rule::unique('reason_categories', 'code')->ignore($this->id)],
            'label'      => ['sometimes', 'string', 'max:255'],
            'label_en'   => ['sometimes', 'string', 'max:255'],
            'icon'       => ['sometimes', 'string', 'max:50'],
            'color'      => ['sometimes', 'string', 'max:20'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('reason-codes.edit');
    }
}
