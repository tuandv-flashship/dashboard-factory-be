<?php

namespace App\Containers\AppSection\Media\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ListMediaFolderTreeRequest extends ParentRequest
{
    protected array $decode = ['exclude_ids.*'];
    
    
    public function rules(): array
    {
        return [
            'exclude_ids' => ['nullable', 'array'],
            'exclude_ids.*' => ['integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('media.index') ?? false;
    }
}
