<?php

namespace App\Containers\AppSection\RequestLog\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class DeleteRequestLogRequest extends ParentRequest
{
    protected array $decode = [
        'request_log_id',
    ];
    
    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()->can('request-log.destroy');
    }
}
