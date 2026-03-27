<?php

namespace App\Containers\AppSection\Shift\UI\API\Transformers;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ShiftTemplateTransformer extends ParentTransformer
{
    protected array $availableIncludes = [
        'details',
    ];

    public function transform(ShiftTemplate $template): array
    {
        $details = $template->relationLoaded('details') ? $template->details : collect();

        return [
            'id'                  => $template->getHashedKey(),
            'name'                => $template->name,
            'color'               => $template->color,
            'description'         => $template->description,
            'sort_order'          => $template->sort_order,
            'status'              => $template->status->value,
            'applies_to_shift_1'  => $template->applies_to_shift_1,
            'applies_to_shift_2'  => $template->applies_to_shift_2,
            'shift_1_time'        => $this->shiftTimeRange($details, 1),
            'shift_2_time'        => $this->shiftTimeRange($details, 2),
            'created_at'          => $template->created_at?->toIsoString(),
            'updated_at'          => $template->updated_at?->toIsoString(),
        ];
    }

    public function includeDetails(ShiftTemplate $template): \League\Fractal\Resource\Collection
    {
        $sorted = $template->details->sortBy([
            fn ($a, $b) => ($a->department?->productionLine?->sort_order ?? 0) <=> ($b->department?->productionLine?->sort_order ?? 0),
            fn ($a, $b) => $a->shift_number <=> $b->shift_number,
            fn ($a, $b) => ($a->department?->sort_order ?? 0) <=> ($b->department?->sort_order ?? 0),
        ])->values();

        return $this->collection(
            $sorted,
            new ShiftTemplateDetailTransformer(),
            'details',
        );
    }

    /**
     * Compute "HH:MM - HH:MM" time range for a given shift number.
     */
    private function shiftTimeRange($details, int $shiftNumber): ?string
    {
        $shiftDetails = $details->where('shift_number', $shiftNumber);

        if ($shiftDetails->isEmpty()) {
            return null;
        }

        $start = substr($shiftDetails->min('start_time'), 0, 5);
        $end   = $shiftDetails->map(fn ($d) => $d->end_time)->max();

        return "{$start} - {$end}";
    }
}
