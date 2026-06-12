<?php

namespace App\Containers\AppSection\Department\UI\API\Transformers;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Machine\UI\API\Transformers\MachineTransformer;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;
use League\Fractal\Resource\Collection;

final class DepartmentTransformer extends ParentTransformer
{
    protected array $defaultIncludes = ['machines'];

    protected array $availableIncludes = ['production_line'];

    public function transform(Department $dept): array
    {
        $data = [
            'id' => $dept->getHashedKey(),
            'code' => $dept->code,
            'label' => $dept->label,
            'label_en' => $dept->label_en,
            'description' => $dept->description,
            'icon' => $dept->icon,
            'unit' => $dept->unit,
            'kpi_per_hour' => $dept->kpi_per_hour,
            'productivity_type' => $dept->productivity_type,
            'sort_order' => $dept->sort_order,
            'is_active' => $dept->is_active,
            'is_hidden' => $dept->is_hidden,
            'is_parent' => $dept->relationLoaded('children') && $dept->children->isNotEmpty(),
            'parent_id' => $dept->parent_id
                ? ($dept->relationLoaded('parent') ? $dept->parent?->getHashedKey() : $dept->parent_id)
                : null,
            'created_at' => $dept->created_at?->toIsoString(),
            'updated_at' => $dept->updated_at?->toIsoString(),
        ];

        $data['available_machines'] = $dept->toAvailableMachines();

        return $data;
    }

    public function includeProductionLine(Department $dept): \League\Fractal\Resource\Item
    {
        return $this->item(
            $dept->productionLine,
            new \App\Containers\AppSection\Production\UI\API\Transformers\ProductionLineTransformer(),
            'production_line',
        );
    }

    public function includeMachines(Department $dept): Collection
    {
        return $this->collection(
            $dept->machines,
            new MachineTransformer(),
            'machine',
        );
    }
}
