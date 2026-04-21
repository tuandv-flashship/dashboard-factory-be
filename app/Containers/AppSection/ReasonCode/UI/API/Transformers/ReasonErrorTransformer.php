<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Transformers;

use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;
use League\Fractal\Resource\Item;

final class ReasonErrorTransformer extends ParentTransformer
{
    protected array $availableIncludes = [
        'category',
        'sub_item',
    ];

    public function transform(ReasonError $error): array
    {
        return [
            'id'         => $error->getHashedKey(),
            'code'       => $error->code,
            'label'      => $error->label,
            'sort_order' => $error->sort_order,
            'is_active'  => $error->is_active,
            'created_at' => $error->created_at?->toIsoString(),
            'updated_at' => $error->updated_at?->toIsoString(),
        ];
    }

    public function includeCategory(ReasonError $error): Item
    {
        return $this->item(
            $error->category,
            new ReasonCategoryTransformer(),
            'category',
        );
    }

    public function includeSubItem(ReasonError $error): Item
    {
        return $this->item(
            $error->subItem,
            new ReasonSubItemTransformer(),
            'sub_item',
        );
    }
}

