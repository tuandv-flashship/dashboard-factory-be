<?php

namespace App\Containers\AppSection\Table\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ColumnVisibilityRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => '',
        'roles' => '',
    ];

    public function rules(): array
    {
        return [
            'model' => ['required', 'string', 'max:50'],
            'columns' => ['required', 'array'],
            'columns.*.visible' => ['required', 'boolean'],
            'columns.*.order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
