<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetOrderByEstimateRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => 'shifts.index',
        'roles'       => 'admin',
    ];

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
