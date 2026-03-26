<?php

namespace App\Containers\AppSection\Department\UI\API\Transformers;

use App\Containers\AppSection\Department\Models\Department;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class DepartmentTransformer extends ParentTransformer
{
    protected array $availableIncludes = ['production_line'];

    public function transform(Department $dept): array
    {
        return [
            'id' => $dept->getHashedKey(),
            'code' => $dept->code,
            'label' => $dept->label,
            'label_en' => $dept->label_en,
            'icon' => $dept->icon,
            'unit' => $dept->unit,
            'kpi_per_hour' => $dept->kpi_per_hour,
            'factory' => $dept->factory,
            'sort_order' => $dept->sort_order,
            'is_active' => $dept->is_active,
            'created_at' => $dept->created_at?->toIsoString(),
            'updated_at' => $dept->updated_at?->toIsoString(),
        ];
    }

    public function includeProductionLine(Department $dept): \League\Fractal\Resource\Item
    {
        return $this->item(
            $dept->productionLine,
            new \App\Containers\AppSection\Production\UI\API\Transformers\ProductionLineTransformer(),
            'production_line',
        );
    }
}
