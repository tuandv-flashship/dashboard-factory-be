<?php

namespace App\Ship\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared request for endpoints that accept date + shift query params.
 * Validates format and range; returns null for missing params.
 */
class ShiftFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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

    /**
     * Get validated date or null.
     */
    public function filterDate(): ?string
    {
        return $this->validated('date');
    }

    /**
     * Get validated shift number or null.
     */
    public function filterShift(): ?int
    {
        $shift = $this->validated('shift');
        return $shift !== null ? (int) $shift : null;
    }
}
