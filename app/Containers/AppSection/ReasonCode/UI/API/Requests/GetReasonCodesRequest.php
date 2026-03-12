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
            'line' => ['sometimes', 'string', 'in:dtf1,dtf2,dtg'],
            'dept' => ['sometimes', 'string', 'in:print,cut,mockup,pack_ship,pick,dtg_print'],
        ];
    }

    public function authorize(): bool
    {
        return $this->check(['is_admin']);
    }
}
