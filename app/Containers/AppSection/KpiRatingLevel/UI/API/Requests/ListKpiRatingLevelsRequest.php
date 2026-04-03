<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Requests;

use App\Containers\AppSection\KpiRatingLevel\Enums\KpiRatingLevelStatus;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rules\Enum;

final class ListKpiRatingLevelsRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', new Enum(KpiRatingLevelStatus::class)],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('kpi-rating-levels.index');
    }
}
