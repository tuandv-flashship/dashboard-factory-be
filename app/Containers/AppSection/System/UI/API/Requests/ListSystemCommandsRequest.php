<?php

namespace App\Containers\AppSection\System\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ListSystemCommandsRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return config('system-commands.enabled') && $this->user()->isSuperAdmin();
    }
}
