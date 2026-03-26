<?php

namespace App\Containers\AppSection\Media\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class CreateMediaFolderRequest extends ParentRequest
{
    protected array $decode = ['parent_id'];
    
    
    public function rules(): array
    {
        return [
            'name' => ['required', 'regex:/^[\\pL\\s\\_\\-0-9]+$/u', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'The name contains invalid characters.',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('folders.create') ?? false;
    }
}
