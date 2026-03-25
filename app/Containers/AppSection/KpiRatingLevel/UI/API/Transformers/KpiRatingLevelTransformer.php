<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Transformers;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class KpiRatingLevelTransformer extends ParentTransformer
{
    protected array $defaultIncludes = [
        'details',
    ];

    public function transform(KpiRatingLevel $ratingLevel): array
    {
        return [
            'id'              => $ratingLevel->getHashedKey(),
            'name'            => $ratingLevel->name,
            'effective_from'  => $ratingLevel->effective_from?->format('Y-m-d'),
            'effective_until' => $ratingLevel->effective_until?->format('Y-m-d'),
            'status'          => $ratingLevel->status->value,
            'description'     => $ratingLevel->description,
            'created_at'      => $ratingLevel->created_at?->toIsoString(),
            'updated_at'      => $ratingLevel->updated_at?->toIsoString(),
        ];
    }

    public function includeDetails(KpiRatingLevel $ratingLevel): \League\Fractal\Resource\Collection
    {
        return $this->collection(
            $ratingLevel->details,
            new KpiRatingLevelDetailTransformer(),
            'details',
        );
    }
}
