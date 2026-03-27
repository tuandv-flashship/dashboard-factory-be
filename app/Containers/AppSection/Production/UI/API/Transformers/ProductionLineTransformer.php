<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Department\UI\API\Transformers\DepartmentTransformer;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ProductionLineTransformer extends ParentTransformer
{
    protected array $availableIncludes = ['departments'];

    public function transform(ProductionLine $line): array
    {
        return [
            'id' => $line->getHashedKey(),
            'code' => $line->code,
            'label' => $line->label,
            'color' => $line->color,
            'subtitle' => $line->subtitle,
            'is_shared' => $line->is_shared,
            'sort_order' => $line->sort_order,
            'is_active' => $line->is_active,
            'created_at' => $line->created_at?->toIsoString(),
            'updated_at' => $line->updated_at?->toIsoString(),
        ];
    }

    public function includeDepartments(ProductionLine $line): \League\Fractal\Resource\Collection
    {
        return $this->collection(
            $line->departments,
            new DepartmentTransformer(),
            'departments',
        );
    }
}
