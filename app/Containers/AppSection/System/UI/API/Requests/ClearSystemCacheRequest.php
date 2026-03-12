<?php

namespace App\Containers\AppSection\System\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rule;

final class ClearSystemCacheRequest extends ParentRequest
{
    protected array $decode = [];
    
    
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in([
                    'clear_cms_cache',
                    'refresh_compiled_views',
                    'clear_config_cache',
                    'clear_route_cache',
                    'clear_event_cache',
                    'clear_log',
                    'clear_all_cache',
                    'optimize',
                    'clear_optimize',
                ]),
            ],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('system.cache');
    }
}
