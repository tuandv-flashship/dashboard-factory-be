<?php

namespace App\Containers\AppSection\Media\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ListMediaFolderListRequest extends ParentRequest
{
    protected array $decode = ['parent_id', 'exclude_ids.*'];
    
    
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'min:0'],
            'exclude_ids' => ['nullable', 'array'],
            'exclude_ids.*' => ['integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('media.index') ?? false;
    }
}
