<?php

namespace App\Containers\AppSection\Media\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetMediaOptionsRequest extends ParentRequest
{
    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('media.index') ?? false;
    }
}
