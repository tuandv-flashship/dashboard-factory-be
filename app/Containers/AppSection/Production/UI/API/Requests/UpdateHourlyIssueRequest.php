<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class UpdateHourlyIssueRequest extends ParentRequest
{
    protected array $decode = ['id'];

    protected array $access = [
        'permissions' => '',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [
            'category'              => ['sometimes', 'string', Rule::exists('reason_categories', 'code')->where('is_active', true)],
            'sub_item'              => ['sometimes', 'string', 'max:200'],
            'error'                 => ['sometimes', 'string', 'max:500'],
            'note'                  => ['nullable', 'string', 'max:2000'],
            'resolved'              => ['sometimes', 'boolean'],
            'resolution'            => ['nullable', 'string', 'max:1000'],
            'productivity_item_id'  => ['sometimes', 'nullable', 'string', 'size:8'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
