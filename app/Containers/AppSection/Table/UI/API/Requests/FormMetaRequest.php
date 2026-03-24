<?php

namespace App\Containers\AppSection\Table\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class FormMetaRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'model'  => ['required', 'string'],
            'action' => ['required', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true; // Permission checked in GetFormMetaAction
    }
}
