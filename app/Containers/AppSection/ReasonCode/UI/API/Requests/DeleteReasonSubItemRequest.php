<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class DeleteReasonSubItemRequest extends ParentRequest
{
    protected array $decode = ['id'];

    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return $this->user()->can('reason-codes.destroy');
    }
}
