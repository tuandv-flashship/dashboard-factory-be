<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetAllLinesHourlyRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => '',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [
            'date'  => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'shift' => ['nullable', 'integer', 'in:1,2,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format'      => 'date phải có format YYYY-MM-DD (VD: 2026-03-11)',
            'date.before_or_equal'  => 'date không được ở tương lai',
            'shift.integer'         => 'shift phải là số nguyên',
            'shift.in'              => 'shift phải là 1, 2, hoặc 3',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('dashboard.view') ?? false;
    }

    public function filterDate(): ?string
    {
        return $this->validated('date');
    }

    public function filterShift(): ?int
    {
        $shift = $this->validated('shift');
        return $shift !== null ? (int) $shift : null;
    }
}
