<?php

namespace App\Containers\AppSection\System\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Supports\SystemCommandRegistry;
use Illuminate\Validation\Rule;

final class RunSystemCommandRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(SystemCommandRegistry::allowedActions())],
        ];
    }

    public function authorize(): bool
    {
        return config('system-commands.enabled') && $this->user()->isSuperAdmin();
    }
}
