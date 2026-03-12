<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Transformers;

use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ReasonSubItemTransformer extends ParentTransformer
{
    public function transform(ReasonSubItem $subItem): array
    {
        return [
            'id' => $subItem->getHashedKey(),
            'code' => $subItem->code,
            'label' => $subItem->label,
            'scope_type' => $subItem->scope_type,
            'scope_line' => $subItem->scope_line,
            'scope_dept' => $subItem->scope_dept,
        ];
    }
}
