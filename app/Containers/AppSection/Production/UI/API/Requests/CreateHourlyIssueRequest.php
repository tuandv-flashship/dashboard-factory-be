<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class CreateHourlyIssueRequest extends ParentRequest
{
    protected array $decode = ['id'];


    protected array $access = [
        'permissions' => '',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [
            'category'   => ['required', 'string', Rule::exists('reason_categories', 'code')->where('is_active', true)],
            'sub_item'   => ['required', 'string', 'max:200'],
            'error'      => ['required', 'string', 'max:500'],
            'note'       => ['nullable', 'string', 'max:2000'],
            'resolved'   => ['sometimes', 'boolean'],
            'resolution' => ['nullable', 'string', 'max:1000'],

        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
