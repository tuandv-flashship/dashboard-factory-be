<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetDefaultKpiRatingLevelRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('kpi-rating-levels.store') ?? false;
    }
}
