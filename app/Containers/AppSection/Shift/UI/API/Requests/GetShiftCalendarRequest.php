<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetShiftCalendarRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'year'  => 'sometimes|integer|min:2020|max:2099',
            'month' => 'sometimes|integer|min:1|max:12',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('shifts.index');
    }
}
