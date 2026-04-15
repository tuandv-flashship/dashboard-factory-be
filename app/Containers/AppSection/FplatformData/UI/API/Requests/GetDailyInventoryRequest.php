<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Requests;

use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rules\Enum;

final class GetDailyInventoryRequest extends ParentRequest
{
    public function rules(): array
    {
        return [
            'date'    => ['sometimes', 'date_format:Y-m-d'],
            'team'    => ['required', new Enum(Team::class)],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        $valid = implode(', ', array_column(Team::cases(), 'value'));

        return [
            'team.required' => "Vui lòng chọn team ({$valid}).",
        ];
    }
}
