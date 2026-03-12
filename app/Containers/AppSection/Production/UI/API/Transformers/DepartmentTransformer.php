<?php

namespace App\Containers\AppSection\Production\UI\API\Transformers;

use App\Containers\AppSection\Production\Models\Department;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class DepartmentTransformer extends ParentTransformer
{
    public function transform(Department $dept): array
    {
        return [
            'id' => $dept->getHashedKey(),
            'code' => $dept->code,
            'label' => $dept->label,
            'label_en' => $dept->label_en,
            'icon' => $dept->icon,
            'unit' => $dept->unit,
        ];
    }
}
