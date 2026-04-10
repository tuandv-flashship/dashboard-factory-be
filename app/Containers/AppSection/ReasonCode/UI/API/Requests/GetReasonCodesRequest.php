<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetReasonCodesRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => '',
        'roles' => '',
    ];

    /**
     * @return array<string, string[]>
     */
    public function rules(): array
    {
        return [
            'line' => ['sometimes', 'string', 'exists:production_lines,code'],
            'dept' => ['sometimes', 'string', 'exists:departments,code'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
