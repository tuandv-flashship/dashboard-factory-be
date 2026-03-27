<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateKpiRatingLevelRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'name'                      => ['required', 'string', 'max:255'],
            'effective_from'            => ['required', 'date'],
            'effective_until'           => ['nullable', 'date', 'after:effective_from'],
            'description'               => ['nullable', 'string'],

            'details'                   => ['required', 'array', 'min:1'],
            'details.*.level_name'      => ['required', 'string', 'max:255', 'distinct'],
            'details.*.bg_color'        => ['required', 'string', 'max:20'],
            'details.*.text_color'      => ['required', 'string', 'max:20'],
            'details.*.min_score'       => ['required', 'numeric', 'min:0', 'max:100'],
            'details.*.operator'        => ['sometimes', 'string', 'in:>=,<'],
            'details.*.is_kpi_threshold'          => ['sometimes', 'boolean'],
            'details.*.is_staff_warning_threshold' => ['sometimes', 'boolean'],
            'details.*.sort_order'      => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('kpi-rating-levels.create');
    }
}
