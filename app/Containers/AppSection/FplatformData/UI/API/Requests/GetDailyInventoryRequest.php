<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetDailyInventoryRequest extends ParentRequest
{
    public function rules(): array
    {
        return [
            'date'    => ['sometimes', 'date_format:Y-m-d'],
            'team'    => ['required', 'in:in,cat,pick,mockup,pack_ship,dtg_pick,dtg_print,dtg_print_split'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'team.required' => 'Vui lòng chọn team (in, cat, pick, mockup, pack_ship, dtg_pick, dtg_print, dtg_print_split).',
        ];
    }
}
