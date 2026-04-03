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
            'factory' => ['required_unless:team,dtg_pick,dtg_print,dtg_print_split', 'nullable', 'in:FLS,PD,fls,pd'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'team.required'       => 'Vui lòng chọn team (in, cat, pick, mockup, pack_ship, dtg_pick, dtg_print, dtg_print_split).',
            'factory.required_unless' => 'Vui lòng chọn factory (FLS hoặc PD) cho team DTF.',
        ];
    }
}
