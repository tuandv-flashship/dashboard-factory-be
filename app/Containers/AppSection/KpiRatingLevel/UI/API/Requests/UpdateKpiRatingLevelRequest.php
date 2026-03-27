<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class UpdateKpiRatingLevelRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'name'                      => ['sometimes', 'string', 'max:255'],
            'effective_from'            => ['sometimes', 'date'],
            'effective_until'           => ['nullable', 'date', 'after:effective_from'],
            'description'               => ['nullable', 'string'],

            'details'                   => ['sometimes', 'array', 'min:1'],
            'details.*.level_name'      => ['required_with:details', 'string', 'max:255', 'distinct'],
            'details.*.bg_color'        => ['required_with:details', 'string', 'max:20'],
            'details.*.text_color'      => ['required_with:details', 'string', 'max:20'],
            'details.*.min_score'       => ['required_with:details', 'numeric', 'min:0', 'max:100'],
            'details.*.operator'        => ['sometimes', 'string', 'in:>=,<'],
            'details.*.requires_reason'      => ['sometimes', 'boolean'],
            'details.*.warn_staff_shortage'  => ['sometimes', 'boolean'],
            'details.*.sort_order'      => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('kpi-rating-levels.edit');
    }
}
