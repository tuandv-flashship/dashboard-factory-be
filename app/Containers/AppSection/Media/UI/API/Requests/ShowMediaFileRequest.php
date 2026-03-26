<?php

namespace App\Containers\AppSection\Media\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ShowMediaFileRequest extends ParentRequest
{
    protected array $decode = [];
    protected function prepareForValidation(): void
    {
        $this->merge([
            'hash' => (string) $this->route('hash'),
            'id' => (string) $this->route('id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'hash' => ['required', 'regex:/^[a-f0-9]{40}$/i'],
            'id' => ['required', 'regex:/^[a-f0-9]+$/i'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

