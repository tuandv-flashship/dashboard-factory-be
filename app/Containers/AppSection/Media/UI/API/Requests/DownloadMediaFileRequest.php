<?php

namespace App\Containers\AppSection\Media\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class DownloadMediaFileRequest extends ParentRequest
{
    protected array $decode = ['folder_id'];
    /**
     * @return array{
     *  url:string,
     *  folder_id:int,
     *  visibility:?string,
     *  access_mode:?string
     * }
     */
    public function downloadInput(): array
    {
        return [
            'url' => (string) $this->input('url'),
            'folder_id' => (int) $this->input('folder_id', 0),
            'visibility' => $this->input('visibility'),
            'access_mode' => $this->input('access_mode'),
        ];
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url'],
            'folder_id' => ['nullable', 'integer', 'min:0'],
            'visibility' => ['nullable', 'string', Rule::in(['public', 'private'])],
            'access_mode' => ['nullable', 'string', Rule::in(['auth', 'signed'])],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('files.create') ?? false;
    }
}
