<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetHotshotOrdersRequest extends ParentRequest
{
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user) {
            return false;
        }

        return $user->hasPermissionTo('shifts.index')
            || $user->hasRole('admin');
    }
}
