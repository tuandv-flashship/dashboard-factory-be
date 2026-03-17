<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Transformers;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ReasonCategoryTransformer extends ParentTransformer
{
    protected array $availableIncludes = [
        'sub_items',
        'errors',
    ];

    public function transform(ReasonCategory $category): array
    {
        return [
            'id' => $category->getHashedKey(),
            'code' => $category->code,
            'label' => $category->label,
            'label_en' => $category->label_en,
            'icon' => $category->icon,
            'color' => $category->color,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
            'created_at' => $category->created_at?->toIsoString(),
            'updated_at' => $category->updated_at?->toIsoString(),
        ];
    }

    public function includeSubItems(ReasonCategory $category): \League\Fractal\Resource\Collection
    {
        return $this->collection(
            $category->subItems,
            new ReasonSubItemTransformer(),
            'sub_items',
        );
    }

    public function includeErrors(ReasonCategory $category): \League\Fractal\Resource\Collection
    {
        return $this->collection(
            $category->errors,
            new ReasonErrorTransformer(),
            'errors',
        );
    }
}
