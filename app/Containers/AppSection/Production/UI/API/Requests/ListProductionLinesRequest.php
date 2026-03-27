<?php

namespace App\Containers\AppSection\Production\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ListProductionLinesRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'dept_factory' => ['sometimes', 'string', 'in:FLS,PD'],
            'dept_active'  => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('production-lines.index');
    }
}
