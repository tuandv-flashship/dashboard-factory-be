<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ReorderReasonErrorsRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'items'              => ['required', 'array', 'min:1'],
            'items.*.id'         => ['required', 'integer'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('reason-codes.edit');
    }
}
