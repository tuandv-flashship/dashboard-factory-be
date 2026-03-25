<?php

namespace App\Containers\AppSection\KpiRatingLevel\Data\Repositories;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevelDetail;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of KpiRatingLevelDetail
 *
 * @extends ParentRepository<TModel>
 */
final class KpiRatingLevelDetailRepository extends ParentRepository
{
    public function model(): string
    {
        return KpiRatingLevelDetail::class;
    }
}
