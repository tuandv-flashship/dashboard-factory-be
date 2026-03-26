<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

/**
 * Copy shifts to target dates.
 *
 * Body: { shift_ids: [1, 2], target_dates: ["2026-03-21", "2026-03-22"] }
 */
final class CopyShiftRequest extends ParentRequest
{
    protected array $decode = [];

    protected array $access = [
        'permissions' => 'shifts.create',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [
            'shift_ids'      => 'required|array|min:1',
            'shift_ids.*'    => 'required|integer|exists:shifts,id',
            'target_dates'   => 'required|array|min:1',
            'target_dates.*' => 'required|date|after_or_equal:today',
        ];
    }

    public function authorize(): bool
    {
        return $this->check(['hasAccess']);
    }

    public function messages(): array
    {
        return [
            'target_dates.*.after_or_equal' => 'Chỉ cho nhân bản các ngày >= hôm nay.',
        ];
    }
}
