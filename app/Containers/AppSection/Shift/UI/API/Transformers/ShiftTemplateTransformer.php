<?php

namespace App\Containers\AppSection\Shift\UI\API\Transformers;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ShiftTemplateTransformer extends ParentTransformer
{
    protected array $defaultIncludes = [
        'details',
    ];

    public function transform(ShiftTemplate $template): array
    {
        return [
            'id'                  => $template->getHashedKey(),
            'name'                => $template->name,
            'color'               => $template->color,
            'description'         => $template->description,
            'sort_order'          => $template->sort_order,
            'status'              => $template->status->value,
            'applies_to_shift_1'  => $template->applies_to_shift_1,
            'applies_to_shift_2'  => $template->applies_to_shift_2,
            'created_at'          => $template->created_at?->toIsoString(),
            'updated_at'          => $template->updated_at?->toIsoString(),
        ];
    }

    public function includeDetails(ShiftTemplate $template): \League\Fractal\Resource\Collection
    {
        return $this->collection(
            $template->details,
            new ShiftTemplateDetailTransformer(),
            'details',
        );
    }
}
