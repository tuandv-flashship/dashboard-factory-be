<?php

namespace App\Containers\AppSection\Media\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class ListMediaRequest extends ParentRequest
{
    protected array $decode = ['folder_id', 'selected_file_id'];
    
    
    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', 'integer', 'min:0'],
            'view_in' => ['nullable', 'string', Rule::in(['all_media', 'trash', 'recent', 'favorites'])],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'max:50'],
            'filter' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'selected_file_id' => ['nullable', 'integer', 'min:1'],
            'include_signed_url' => ['nullable', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('media.index') ?? false;
    }
}
