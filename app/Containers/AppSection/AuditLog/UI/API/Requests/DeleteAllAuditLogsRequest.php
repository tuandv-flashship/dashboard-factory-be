<?php

namespace App\Containers\AppSection\AuditLog\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class DeleteAllAuditLogsRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()->can('audit-log.destroy');
    }
}
