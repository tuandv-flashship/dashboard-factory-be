<?php

namespace App\Containers\AppSection\System\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetSystemInfoRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()->can('system.info');
    }
}
