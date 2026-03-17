<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Transformers;

use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ReasonErrorTransformer extends ParentTransformer
{
    protected array $availableIncludes = [
        'category',
    ];

    public function transform(ReasonError $error): array
    {
        return [
            'id' => $error->getHashedKey(),
            'code' => $error->code,
            'label' => $error->label,
            'scope_dept' => $error->scope_dept,
            'sort_order' => $error->sort_order,
            'is_active' => $error->is_active,
            'created_at' => $error->created_at?->toIsoString(),
            'updated_at' => $error->updated_at?->toIsoString(),
        ];
    }

    public function includeCategory(ReasonError $error): \League\Fractal\Resource\Item
    {
        return $this->item(
            $error->category,
            new ReasonCategoryTransformer(),
            'category',
        );
    }
}
