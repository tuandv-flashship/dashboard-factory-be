<?php

namespace App\Containers\AppSection\Media\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class UploadMediaFileRequest extends ParentRequest
{
    protected array $decode = ['folder_id'];
    /**
     * @return array{
     *  folder_id:int,
     *  visibility:?string,
     *  access_mode:?string,
     *  dzuuid:?string,
     *  dzchunkindex:?int,
     *  dztotalchunkcount:int,
     *  dztotalfilesize:int,
     *  dzchunksize:int,
     *  filename:?string,
     *  has_chunk_index:bool,
     *  has_chunk_uuid:bool
     * }
     */
    public function uploadInput(): array
    {
        return [
            'folder_id' => (int) $this->input('folder_id', 0),
            'visibility' => $this->input('visibility'),
            'access_mode' => $this->input('access_mode'),
            'dzuuid' => $this->input('dzuuid'),
            'dzchunkindex' => $this->has('dzchunkindex') ? (int) $this->input('dzchunkindex') : null,
            'dztotalchunkcount' => (int) $this->input('dztotalchunkcount', 1),
            'dztotalfilesize' => (int) $this->input('dztotalfilesize', 0),
            'dzchunksize' => (int) $this->input('dzchunksize', 0),
            'filename' => $this->input('filename'),
            'has_chunk_index' => $this->has('dzchunkindex'),
            'has_chunk_uuid' => $this->filled('dzuuid'),
        ];
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'folder_id' => ['nullable', 'integer', 'min:0'],
            'visibility' => ['nullable', 'string', Rule::in(['public', 'private'])],
            'access_mode' => ['nullable', 'string', Rule::in(['auth', 'signed'])],
            'dzuuid' => ['nullable', 'string'],
            'dzchunkindex' => ['nullable', 'integer', 'min:0'],
            'dztotalchunkcount' => ['nullable', 'integer', 'min:1'],
            'dztotalfilesize' => ['nullable', 'integer', 'min:0'],
            'dzchunksize' => ['nullable', 'integer', 'min:0'],
            'filename' => ['nullable', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('files.create') ?? false;
    }
}
