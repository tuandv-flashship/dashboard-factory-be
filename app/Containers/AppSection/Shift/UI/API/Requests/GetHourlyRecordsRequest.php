<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetHourlyRecordsRequest extends ParentRequest
{
    protected array $decode = ['id'];

    protected array $access = [
        'permissions' => 'shifts.index',
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
