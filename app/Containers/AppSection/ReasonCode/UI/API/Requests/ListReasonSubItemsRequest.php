<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class ListReasonSubItemsRequest extends ParentRequest
{
    protected array $decode = ['category_id'];

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()->can('reason-codes.index');
    }
}
