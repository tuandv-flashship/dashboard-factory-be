<?php

namespace App\Containers\AppSection\RequestLog\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetRequestLogWidgetRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('request-log.index');
    }
}
