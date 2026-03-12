<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ProductionLineTransformer extends ParentTransformer
{
    protected array $defaultIncludes = ['departments'];

    public function transform(ProductionLine $line): array
    {
        return [
            'id' => $line->getHashedKey(),
            'code' => $line->code,
            'label' => $line->label,
            'color' => $line->color,
            'building' => $line->building,
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
