<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetActiveKpiRatingLevelRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        // Public endpoint — no auth required
        return true;
    }
}
