<?php

namespace App\Containers\AppSection\Order\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetOrderSummaryHistoryRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => '',
        'roles'       => '',
    ];

    public function rules(): array
    {
        return [
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'line' => ['nullable', 'string', 'in:dtf,dtg'],
        ];
    }

    public function messages(): array
    {
        return [
            'days.integer' => 'days phải là số nguyên',
            'days.min'     => 'days phải >= 1',
            'days.max'     => 'days không được vượt quá 90',
            'line.in'      => 'line phải là dtf hoặc dtg',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('dashboard.view') ?? false;
    }

    public function filterDays(): int
    {
        return (int) ($this->validated('days') ?? 10);
    }

    public function filterLine(): ?string
    {
        return $this->validated('line');
    }
}
