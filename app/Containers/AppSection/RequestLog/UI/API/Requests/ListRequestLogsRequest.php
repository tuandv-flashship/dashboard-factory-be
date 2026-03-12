<?php

namespace App\Containers\AppSection\RequestLog\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class ListRequestLogsRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'searchFields' => ['nullable', 'string', 'max:255'],
            'searchJoin' => ['nullable', Rule::in(['and', 'or'])],
            'orderBy' => ['nullable', Rule::in(['id', 'url', 'status_code', 'created_at'])],
            'sortedBy' => ['nullable', Rule::in(['asc', 'desc'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('request-log.index');
    }
}
