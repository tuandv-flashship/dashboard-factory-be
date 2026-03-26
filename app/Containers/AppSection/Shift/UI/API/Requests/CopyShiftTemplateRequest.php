<?php

namespace App\Containers\AppSection\Shift\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CopyShiftTemplateRequest extends ParentRequest
{
    protected array $decode = ['id'];

    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()->can('shift-templates.create');
    }
}
