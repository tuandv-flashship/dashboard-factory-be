<?php

namespace App\Containers\AppSection\Icon\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ListIconsRequest extends ParentRequest
{
    protected array $decode = [];


    public function rules(): array
    {
        $maxPerPage = (int) config('icon.max_per_page', 500);

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', "max:$maxPerPage"],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
