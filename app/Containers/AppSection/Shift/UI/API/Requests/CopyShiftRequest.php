<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CopyShiftRequest extends ParentRequest
{
    protected array $decode = ['id'];

    protected array $access = [
        'permissions' => 'shifts.create',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [
            'target_dates'   => 'required|array|min:1',
            'target_dates.*' => 'required|date',
        ];
    }

    public function authorize(): bool
    {
        return $this->check(['hasAccess']);
    }
}
