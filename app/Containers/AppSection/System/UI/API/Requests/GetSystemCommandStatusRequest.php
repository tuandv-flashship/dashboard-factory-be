<?php

namespace App\Containers\AppSection\System\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetSystemCommandStatusRequest extends ParentRequest
{
    protected array $decode = [];
    protected function prepareForValidation(): void
    {
        $this->merge([
            'job_id' => $this->route('job_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'job_id' => ['required', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return config('system-commands.enabled') && $this->user()->isSuperAdmin();
    }
}
