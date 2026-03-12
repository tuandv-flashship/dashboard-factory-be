<?php

namespace App\Containers\AppSection\ReasonCode\UI\API\Transformers;

use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ReasonErrorTransformer extends ParentTransformer
{
    public function transform(ReasonError $error): array
    {
        return [
            'id' => $error->getHashedKey(),
            'code' => $error->code,
            'label' => $error->label,
            'scope_dept' => $error->scope_dept,
        ];
    }
}
