<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class DeleteShiftRequest extends ParentRequest
{
    protected array $decode = ['id'];

    protected array $access = [
        'permissions' => 'shifts.destroy',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->check(['hasAccess']);
    }
}
