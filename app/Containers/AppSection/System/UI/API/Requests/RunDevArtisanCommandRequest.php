<?php

namespace App\Containers\AppSection\System\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class RunDevArtisanCommandRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'command' => ['required', 'string', 'max:500'],
            'options' => ['sometimes', 'array'],
            'options.*' => ['nullable'],
        ];
    }

    public function authorize(): bool
    {
        return !app()->isProduction()
            && $this->user()
            && $this->user()->isSuperAdmin();
    }
}
