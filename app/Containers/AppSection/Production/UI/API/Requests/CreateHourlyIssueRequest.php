<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateHourlyIssueRequest extends ParentRequest
{
    protected bool|array $decode = ['id'];

    protected array $access = [
        'permissions' => '',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'in:machine,human,material,process'],
            'sub_item' => ['required', 'string', 'max:200'],
            'error'    => ['required', 'string', 'max:500'],
            'note'     => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
