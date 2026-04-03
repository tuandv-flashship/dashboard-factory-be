<?php

namespace App\Containers\AppSection\KpiRatingLevel\Data\Criteria;

use App\Containers\AppSection\KpiRatingLevel\Enums\KpiRatingLevelStatus;
use App\Ship\Parents\Criteria\Criteria as ParentCriteria;
use Illuminate\Support\Carbon;
use Prettus\Repository\Contracts\RepositoryInterface as PrettusRepositoryInterface;

final class KpiRatingLevelStatusCriteria extends ParentCriteria
{
    public function __construct(
        private readonly ?KpiRatingLevelStatus $status = null,
    ) {}

    public function apply($model, PrettusRepositoryInterface $repository)
    {
        if ($this->status === null) {
            return $model;
        }

        $today = Carbon::today();

        return match ($this->status) {
            KpiRatingLevelStatus::PENDING => $model->where('effective_from', '>', $today),
            KpiRatingLevelStatus::ACTIVE  => $model
                ->where('effective_from', '<=', $today)
                ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $today)),
            KpiRatingLevelStatus::EXPIRED => $model->where('effective_until', '<', $today),
        };
    }
}
