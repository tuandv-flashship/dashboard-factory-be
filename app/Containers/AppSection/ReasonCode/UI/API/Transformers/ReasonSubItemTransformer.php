<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Transformers;

use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;

final class ReasonSubItemTransformer extends ParentTransformer
{
    protected array $availableIncludes = [
        'category',
        'errors',
    ];

    public function transform(ReasonSubItem $subItem): array
    {
        return [
            'id'         => $subItem->getHashedKey(),
            'code'       => $subItem->code,
            'label'      => $subItem->label,
            'scope_type' => $subItem->scope_type,
            'scope_line' => $subItem->scope_line,
            'scope_dept' => $subItem->scope_dept,
            'sort_order' => $subItem->sort_order,
            'is_active'  => $subItem->is_active,
            'created_at' => $subItem->created_at?->toIsoString(),
            'updated_at' => $subItem->updated_at?->toIsoString(),
        ];
    }

    public function includeCategory(ReasonSubItem $subItem): Item
    {
        return $this->item(
            $subItem->category,
            new ReasonCategoryTransformer(),
            'category',
        );
    }

    public function includeErrors(ReasonSubItem $subItem): Collection
    {
        return $this->collection(
            $subItem->errors,
            new ReasonErrorTransformer(),
            'errors',
        );
    }
}

